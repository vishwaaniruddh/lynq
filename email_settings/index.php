    <?php
/**
 * Email Setting Management Page
 * Single page for configuring TO and CC emails against:
 * 1) Individual sites (Weight 1 - Highest)
 * 2) Sites by cities (Weight 2)
 * 3) Sites by state (Weight 3)
 * 4) Sites by zone (Weight 4 - Broadest)
 * 
 * Hierarchy Precedence: Individual > City > State > Zone
 */

require_once __DIR__ . '/../config/autoload.php';
require_once __DIR__ . '/../utils/LocationParser.php';

$sessionService = new SessionService();
if (!$sessionService->isLoggedIn()) {
    header('Location: ../views/auth/login.php');
    exit;
}

$currentUser = $sessionService->getCurrentUser();
if (!isAdvUser()) {
    header('Location: ../dashboard.php?error=access_denied');
    exit;
}

$baseUrl = '..';
$pageTitle = 'Email Setting - ADV CRM';
$currentPage = 'email_settings';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Email Setting']
];

$db = Database::getInstance()->getConnection();
$emailModel = new SiteEmailSetting();

// 1. Fetch all configured email settings
$siteEmailSettings = $emailModel->getByType('site');
$cityEmailSettings = $emailModel->getByType('city');
$stateEmailSettings = $emailModel->getByType('state');
$zoneEmailSettings = $emailModel->getByType('zone');
$counts = $emailModel->getCounts();

// 2. Fetch reference data for dropdowns
// Sites list
$stmtSites = $db->query("
    SELECT id, site_name, city, state, zone, bank_name, address 
    FROM sites 
    WHERE status != 'deleted' 
    ORDER BY site_name ASC
");
$allSites = $stmtSites->fetchAll(PDO::FETCH_ASSOC);

// Auto-fill any missing city/state/zone from address for sites in list
foreach ($allSites as &$st) {
    if ((empty($st['city']) || empty($st['state']) || empty($st['zone'])) && !empty($st['address'])) {
        $p = LocationParser::parseFromAddress($st['address'], $st['state'] ?? '', $st['city'] ?? '');
        if (empty($st['city']) && !empty($p['city'])) $st['city'] = $p['city'];
        if (empty($st['state']) && !empty($p['state'])) $st['state'] = $p['state'];
        if (empty($st['zone']) && !empty($p['zone'])) $st['zone'] = $p['zone'];
    }
}
unset($st);

// States list
$stmtStates = $db->query("
    SELECT s.id, s.name, z.name as zone_name 
    FROM states s 
    LEFT JOIN zones z ON s.zone_id = z.id 
    WHERE s.status = 'active' 
    ORDER BY s.name ASC
");
$allStates = $stmtStates->fetchAll(PDO::FETCH_ASSOC);

$stateNamesInStates = array_map(function($s) { return strtolower(trim($s['name'])); }, $allStates);
$stmtDistinctSiteStates = $db->query("SELECT DISTINCT state FROM sites WHERE state IS NOT NULL AND TRIM(state) != ''");
while ($r = $stmtDistinctSiteStates->fetch(PDO::FETCH_ASSOC)) {
    $stName = trim($r['state']);
    if (!in_array(strtolower($stName), $stateNamesInStates, true)) {
        $allStates[] = ['id' => null, 'name' => $stName, 'zone_name' => ''];
        $stateNamesInStates[] = strtolower($stName);
    }
}
usort($allStates, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

// Cities list
$stmtCities = $db->query("
    SELECT c.id, c.name, s.name as state_name 
    FROM cities c 
    LEFT JOIN states s ON c.state_id = s.id 
    WHERE c.status = 'active' 
    ORDER BY c.name ASC
");
$allCities = $stmtCities->fetchAll(PDO::FETCH_ASSOC);

$cityNamesInCities = array_map(function($c) { return strtolower(trim($c['name'])); }, $allCities);
$stmtDistinctSiteCities = $db->query("SELECT DISTINCT city, state FROM sites WHERE city IS NOT NULL AND TRIM(city) != ''");
while ($r = $stmtDistinctSiteCities->fetch(PDO::FETCH_ASSOC)) {
    $cName = trim($r['city']);
    if (!in_array(strtolower($cName), $cityNamesInCities, true)) {
        $allCities[] = ['id' => null, 'name' => $cName, 'state_name' => $r['state'] ?? ''];
        $cityNamesInCities[] = strtolower($cName);
    }
}
usort($allCities, function($a, $b) { return strcasecmp($a['name'], $b['name']); });

// Zones list
$stmtZones = $db->query("SELECT id, name FROM zones WHERE status = 'active' ORDER BY name ASC");
$allZones = $stmtZones->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="space-y-6">
    <!-- Top Hero / Header Section -->
    <div class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 rounded-2xl shadow-xl border border-indigo-800/40">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div class="flex items-center space-x-4">
                <span class="w-12 h-12 rounded-xl bg-indigo-500/20 text-indigo-400 border border-indigo-400/30 flex items-center justify-center text-2xl shadow-inner">
                    <i class="fas fa-envelope-open-text"></i>
                </span>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-bold tracking-wider text-indigo-300 uppercase px-2 py-0.5 rounded bg-indigo-900/60 border border-indigo-700/50">
                            Notification Routing Engine
                        </span>
                        <span class="text-[10px] font-bold text-amber-300 bg-amber-900/40 border border-amber-600/40 px-2 py-0.5 rounded flex items-center gap-1">
                            <i class="fas fa-sort-amount-down text-[9px]"></i> Priority: Individual &gt; City &gt; State &gt; Zone
                        </span>
                    </div>
                    <h1 class="text-2xl font-extrabold text-white tracking-tight mt-1">Email Setting</h1>
                    <p class="text-xs text-indigo-200/80 mt-0.5">
                        Configure TO and CC recipient routing with strict hierarchy weight: Individual Sites &gt; Cities &gt; States &gt; Zones
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button" onclick="openEmailModal()" 
                    class="px-4 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-indigo-600/30 transition flex items-center gap-2 transform active:scale-95">
                    <i class="fas fa-plus"></i>
                    <span>Add Email Setting</span>
                </button>
            </div>
        </div>

        <!-- Quick Stats Cards Row (Ordered by Weight: Site > City > State > Zone) -->
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-5 gap-3 mt-6 pt-5 border-t border-indigo-800/40">
            <!-- 1. Individual Sites (Weight 1) -->
            <div class="bg-white/5 backdrop-blur-sm rounded-xl p-3 border border-indigo-400/30 relative">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] uppercase font-bold text-indigo-300">1. Individual Sites</span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-indigo-500/30 text-indigo-200">Weight: 1</span>
                </div>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="text-xl font-black text-white" id="stat-site-count"><?php echo $counts['site']['total']; ?></span>
                    <span class="text-[11px] text-emerald-400 font-semibold" id="stat-site-active"><?php echo $counts['site']['active']; ?> active</span>
                </div>
            </div>

            <!-- 2. By Cities (Weight 2) -->
            <div class="bg-white/5 backdrop-blur-sm rounded-xl p-3 border border-cyan-400/30 relative">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] uppercase font-bold text-cyan-300">2. By Cities</span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-cyan-500/30 text-cyan-200">Weight: 2</span>
                </div>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="text-xl font-black text-white" id="stat-city-count"><?php echo $counts['city']['total']; ?></span>
                    <span class="text-[11px] text-emerald-400 font-semibold" id="stat-city-active"><?php echo $counts['city']['active']; ?> active</span>
                </div>
            </div>

            <!-- 3. By State (Weight 3) -->
            <div class="bg-white/5 backdrop-blur-sm rounded-xl p-3 border border-teal-400/30 relative">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] uppercase font-bold text-teal-300">3. By State</span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-teal-500/30 text-teal-200">Weight: 3</span>
                </div>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="text-xl font-black text-white" id="stat-state-count"><?php echo $counts['state']['total']; ?></span>
                    <span class="text-[11px] text-emerald-400 font-semibold" id="stat-state-active"><?php echo $counts['state']['active']; ?> active</span>
                </div>
            </div>

            <!-- 4. By Zone (Weight 4) -->
            <div class="bg-white/5 backdrop-blur-sm rounded-xl p-3 border border-purple-400/30 relative">
                <div class="flex items-center justify-between">
                    <span class="text-[10px] uppercase font-bold text-purple-300">4. By Zone</span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-purple-500/30 text-purple-200">Weight: 4</span>
                </div>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="text-xl font-black text-white" id="stat-zone-count"><?php echo $counts['zone']['total']; ?></span>
                    <span class="text-[11px] text-emerald-400 font-semibold" id="stat-zone-active"><?php echo $counts['zone']['active']; ?> active</span>
                </div>
            </div>

            <!-- Total Configured -->
            <div class="bg-indigo-600/20 backdrop-blur-sm rounded-xl p-3 border border-indigo-400/30 col-span-2 sm:col-span-1">
                <div class="text-[10px] uppercase font-bold text-indigo-200">Total Configured</div>
                <div class="flex items-baseline justify-between mt-1">
                    <span class="text-xl font-black text-indigo-100" id="stat-all-count"><?php echo $counts['all']['total']; ?></span>
                    <span class="text-[11px] text-indigo-300 font-semibold" id="stat-all-active"><?php echo $counts['all']['active']; ?> active</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Navigation Tabs (Ordered by Weight: Individual > City > State > Zone) -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200/80 p-2">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-2 px-2">
            <nav class="flex flex-wrap gap-1.5" id="emailTabs" role="tablist">
                <!-- Tab 1: Individual Sites (Weight 1) -->
                <button type="button" onclick="switchTab('site')" id="tab-btn-site" 
                    class="tab-btn active px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 bg-indigo-50 text-indigo-700 shadow-sm border border-indigo-200/60">
                    <i class="fas fa-map-pin text-indigo-500"></i>
                    <span>1) Individual Sites</span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-indigo-100 text-indigo-700">Weight: 1</span>
                    <span class="tab-badge px-2 py-0.5 rounded-full text-[10px] font-black bg-indigo-200/70 text-indigo-800" id="badge-site">
                        <?php echo $counts['site']['total']; ?>
                    </span>
                </button>

                <!-- Tab 2: Sites by Cities (Weight 2) -->
                <button type="button" onclick="switchTab('city')" id="tab-btn-city" 
                    class="tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                    <i class="fas fa-city text-cyan-500"></i>
                    <span>2) Sites by Cities</span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-cyan-100 text-cyan-700">Weight: 2</span>
                    <span class="tab-badge px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-200 text-slate-700" id="badge-city">
                        <?php echo $counts['city']['total']; ?>
                    </span>
                </button>

                <!-- Tab 3: Sites by State (Weight 3) -->
                <button type="button" onclick="switchTab('state')" id="tab-btn-state" 
                    class="tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                    <i class="fas fa-map text-teal-500"></i>
                    <span>3) Sites by State</span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-teal-100 text-teal-700">Weight: 3</span>
                    <span class="tab-badge px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-200 text-slate-700" id="badge-state">
                        <?php echo $counts['state']['total']; ?>
                    </span>
                </button>

                <!-- Tab 4: Sites by Zone (Weight 4) -->
                <button type="button" onclick="switchTab('zone')" id="tab-btn-zone" 
                    class="tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 text-slate-600 hover:text-slate-900 hover:bg-slate-100">
                    <i class="fas fa-layer-group text-purple-500"></i>
                    <span>4) Sites by Zone</span>
                    <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-purple-100 text-purple-700">Weight: 4</span>
                    <span class="tab-badge px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-200 text-slate-700" id="badge-zone">
                        <?php echo $counts['zone']['total']; ?>
                    </span>
                </button>

                <!-- Tab 5: Site Recipient Resolver -->
                <button type="button" onclick="switchTab('resolver')" id="tab-btn-resolver" 
                    class="tab-btn px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center gap-2 text-slate-600 hover:text-indigo-600 hover:bg-indigo-50/50">
                    <i class="fas fa-calculator text-amber-500"></i>
                    <span>Site Recipient Resolver (Test)</span>
                    <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-300">Live</span>
                </button>
            </nav>

            <!-- Search in active tab -->
            <div class="relative min-w-[240px]" id="tabSearchWrapper">
                <input type="text" id="tabSearchInput" onkeyup="filterTable(this.value)" 
                    placeholder="Search in table..." 
                    class="w-full pl-9 pr-3 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-indigo-400 focus:bg-white outline-none transition">
                <i class="fas fa-search absolute left-3 top-2.5 text-xs text-slate-400"></i>
            </div>
        </div>

        <!-- Tab 1: Individual Sites (Weight 1 - Highest) -->
        <div id="tab-content-site" class="tab-panel p-4">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-map-pin text-indigo-500"></i>
                            <span>Individual Sites Email Setting</span>
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-indigo-100 text-indigo-800 border border-indigo-200">
                            Weight: 1 (Highest Precedence)
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Direct TO and CC emails assigned specifically to individual sites</p>
                </div>
                <button type="button" onclick="openEmailModal('site')" class="px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5 border border-indigo-200">
                    <i class="fas fa-plus"></i> Add Site Email
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200/80">
                <table class="w-full text-left border-collapse email-table" id="table-site">
                    <thead>
                        <tr class="bg-slate-50/80 text-[11px] font-bold text-slate-600 border-b border-slate-200 uppercase tracking-wider">
                            <th class="py-3 px-4">Site Name</th>
                            <th class="py-3 px-4">Location Context</th>
                            <th class="py-3 px-4">TO Recipient Emails</th>
                            <th class="py-3 px-4">CC Recipient Emails</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4">Remarks</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        <?php if (empty($siteEmailSettings)): ?>
                            <tr class="empty-row">
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <i class="fas fa-envelope-open text-3xl text-slate-300 mb-2 block"></i>
                                    <p class="font-semibold text-slate-600">No individual site email settings configured yet</p>
                                    <p class="text-xs text-slate-400 mt-1">Click "Add Site Email" to configure TO and CC recipients for a site.</p>
                                    <button type="button" onclick="openEmailModal('site')" class="mt-3 px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-xs font-bold hover:bg-indigo-500 transition">
                                        <i class="fas fa-plus mr-1"></i> Add Email Setting
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($siteEmailSettings as $row): 
                                $toEmails = SiteEmailSetting::parseEmails($row['to_emails']);
                                $ccEmails = SiteEmailSetting::parseEmails($row['cc_emails']);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition setting-row" data-id="<?php echo $row['id']; ?>" data-type="site">
                                <td class="py-3 px-4 font-bold text-indigo-700 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5">
                                        <i class="fas fa-map-marker-alt text-indigo-400"></i>
                                        <span class="target-name"><?php echo htmlspecialchars($row['target_name']); ?></span>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-500 whitespace-nowrap">
                                    <?php 
                                    $siteMeta = null;
                                    foreach ($allSites as $s) {
                                        if (strcasecmp($s['site_name'], $row['target_name']) === 0 || $s['id'] == $row['target_id']) {
                                            $siteMeta = $s;
                                            break;
                                        }
                                    }
                                    if ($siteMeta): ?>
                                        <div class="text-[11px] leading-tight">
                                            <span class="font-medium text-slate-700"><?php echo htmlspecialchars($siteMeta['city'] ?? ''); ?></span>, 
                                            <span><?php echo htmlspecialchars($siteMeta['state'] ?? ''); ?></span>
                                            <?php if (!empty($siteMeta['zone'])): ?>
                                                <span class="text-[10px] text-purple-600 font-bold ml-1">(<?php echo htmlspecialchars($siteMeta['zone']); ?> Zone)</span>
                                            <?php endif; ?>
                                            <?php if (!empty($siteMeta['bank_name'])): ?>
                                                <div class="text-[10px] text-slate-400 truncate max-w-[180px]"><?php echo htmlspecialchars($siteMeta['bank_name']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400 italic">Custom Site</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        <?php foreach ($toEmails as $em): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-200/60">
                                                <i class="fas fa-at text-[9px] text-blue-400"></i> <?php echo htmlspecialchars($em); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        <?php if (empty($ccEmails)): ?>
                                            <span class="text-slate-400 italic text-[11px]">None</span>
                                        <?php else: ?>
                                            <?php foreach ($ccEmails as $em): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-purple-50 text-purple-700 border border-purple-200/60">
                                                    <i class="fas fa-copy text-[9px] text-purple-400"></i> <?php echo htmlspecialchars($em); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <button type="button" onclick="toggleStatus(<?php echo $row['id']; ?>)" 
                                        class="status-btn inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold transition <?php echo $row['status'] === 'active' ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $row['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span>
                                        <span><?php echo ucfirst($row['status']); ?></span>
                                    </button>
                                </td>
                                <td class="py-3 px-4 text-slate-500 text-[11px] max-w-xs truncate">
                                    <?php echo htmlspecialchars($row['remarks'] ?? '—'); ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" onclick="editSetting(<?php echo $row['id']; ?>)" class="p-1.5 text-indigo-600 hover:bg-indigo-50 rounded-lg transition" title="Edit Setting">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button type="button" onclick="deleteSetting(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['target_name'])); ?>')" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete Setting">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 2: Sites by Cities (Weight 2) -->
        <div id="tab-content-city" class="tab-panel p-4 hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-city text-cyan-500"></i>
                            <span>Sites by Cities Email Setting</span>
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-cyan-100 text-cyan-800 border border-cyan-200">
                            Weight: 2 (Precedes State &amp; Zone)
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Emails configured here apply automatically to all sites located in the chosen city</p>
                </div>
                <button type="button" onclick="openEmailModal('city')" class="px-3 py-1.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5 border border-cyan-200">
                    <i class="fas fa-plus"></i> Add City Email
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200/80">
                <table class="w-full text-left border-collapse email-table" id="table-city">
                    <thead>
                        <tr class="bg-slate-50/80 text-[11px] font-bold text-slate-600 border-b border-slate-200 uppercase tracking-wider">
                            <th class="py-3 px-4">City Name</th>
                            <th class="py-3 px-4">State</th>
                            <th class="py-3 px-4">TO Recipient Emails</th>
                            <th class="py-3 px-4">CC Recipient Emails</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4">Remarks</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        <?php if (empty($cityEmailSettings)): ?>
                            <tr class="empty-row">
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <i class="fas fa-city text-3xl text-slate-300 mb-2 block"></i>
                                    <p class="font-semibold text-slate-600">No city-level email settings configured yet</p>
                                    <p class="text-xs text-slate-400 mt-1">Configure emails for a specific city to notify local city coordinators and teams.</p>
                                    <button type="button" onclick="openEmailModal('city')" class="mt-3 px-3 py-1.5 bg-cyan-600 text-white rounded-lg text-xs font-bold hover:bg-cyan-500 transition">
                                        <i class="fas fa-plus mr-1"></i> Add City Email Setting
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($cityEmailSettings as $row): 
                                $toEmails = SiteEmailSetting::parseEmails($row['to_emails']);
                                $ccEmails = SiteEmailSetting::parseEmails($row['cc_emails']);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition setting-row" data-id="<?php echo $row['id']; ?>" data-type="city">
                                <td class="py-3 px-4 font-bold text-cyan-800 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5">
                                        <i class="fas fa-city text-cyan-500"></i>
                                        <span class="target-name"><?php echo htmlspecialchars($row['target_name']); ?></span>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-500 whitespace-nowrap">
                                    <?php 
                                    $cityState = '';
                                    foreach ($allCities as $ct) {
                                        if (strcasecmp($ct['name'], $row['target_name']) === 0) {
                                            $cityState = $ct['state_name'] ?? '';
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="text-slate-600 font-medium"><?php echo htmlspecialchars($cityState ?: 'City'); ?></span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        <?php foreach ($toEmails as $em): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-200/60">
                                                <i class="fas fa-at text-[9px] text-blue-400"></i> <?php echo htmlspecialchars($em); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        <?php if (empty($ccEmails)): ?>
                                            <span class="text-slate-400 italic text-[11px]">None</span>
                                        <?php else: ?>
                                            <?php foreach ($ccEmails as $em): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-purple-50 text-purple-700 border border-purple-200/60">
                                                    <i class="fas fa-copy text-[9px] text-purple-400"></i> <?php echo htmlspecialchars($em); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <button type="button" onclick="toggleStatus(<?php echo $row['id']; ?>)" 
                                        class="status-btn inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold transition <?php echo $row['status'] === 'active' ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $row['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span>
                                        <span><?php echo ucfirst($row['status']); ?></span>
                                    </button>
                                </td>
                                <td class="py-3 px-4 text-slate-500 text-[11px] max-w-xs truncate">
                                    <?php echo htmlspecialchars($row['remarks'] ?? '—'); ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" onclick="editSetting(<?php echo $row['id']; ?>)" class="p-1.5 text-cyan-600 hover:bg-cyan-50 rounded-lg transition" title="Edit Setting">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button type="button" onclick="deleteSetting(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['target_name'])); ?>')" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete Setting">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 3: Sites by State (Weight 3) -->
        <div id="tab-content-state" class="tab-panel p-4 hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-map text-teal-500"></i>
                            <span>Sites by State Email Setting</span>
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-teal-100 text-teal-800 border border-teal-200">
                            Weight: 3 (Precedes Zone)
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Emails configured here apply automatically to all sites situated in the respective state</p>
                </div>
                <button type="button" onclick="openEmailModal('state')" class="px-3 py-1.5 bg-teal-50 hover:bg-teal-100 text-teal-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5 border border-teal-200">
                    <i class="fas fa-plus"></i> Add State Email
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200/80">
                <table class="w-full text-left border-collapse email-table" id="table-state">
                    <thead>
                        <tr class="bg-slate-50/80 text-[11px] font-bold text-slate-600 border-b border-slate-200 uppercase tracking-wider">
                            <th class="py-3 px-4">State Name</th>
                            <th class="py-3 px-4">Zone / Region</th>
                            <th class="py-3 px-4">TO Recipient Emails</th>
                            <th class="py-3 px-4">CC Recipient Emails</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4">Remarks</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        <?php if (empty($stateEmailSettings)): ?>
                            <tr class="empty-row">
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <i class="fas fa-map text-3xl text-slate-300 mb-2 block"></i>
                                    <p class="font-semibold text-slate-600">No state-level email settings configured yet</p>
                                    <p class="text-xs text-slate-400 mt-1">Configure emails that will be sent for all sites in a particular state.</p>
                                    <button type="button" onclick="openEmailModal('state')" class="mt-3 px-3 py-1.5 bg-teal-600 text-white rounded-lg text-xs font-bold hover:bg-teal-500 transition">
                                        <i class="fas fa-plus mr-1"></i> Add State Email Setting
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stateEmailSettings as $row): 
                                $toEmails = SiteEmailSetting::parseEmails($row['to_emails']);
                                $ccEmails = SiteEmailSetting::parseEmails($row['cc_emails']);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition setting-row" data-id="<?php echo $row['id']; ?>" data-type="state">
                                <td class="py-3 px-4 font-bold text-teal-800 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5">
                                        <i class="fas fa-map text-teal-500"></i>
                                        <span class="target-name"><?php echo htmlspecialchars($row['target_name']); ?></span>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-500 whitespace-nowrap">
                                    <?php 
                                    $stZone = '';
                                    foreach ($allStates as $st) {
                                        if (strcasecmp($st['name'], $row['target_name']) === 0) {
                                            $stZone = $st['zone_name'] ?? '';
                                            break;
                                        }
                                    }
                                    ?>
                                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-600">
                                        <?php echo !empty($stZone) ? htmlspecialchars($stZone . ' Zone') : 'State Region'; ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        <?php foreach ($toEmails as $em): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-200/60">
                                                <i class="fas fa-at text-[9px] text-blue-400"></i> <?php echo htmlspecialchars($em); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        <?php if (empty($ccEmails)): ?>
                                            <span class="text-slate-400 italic text-[11px]">None</span>
                                        <?php else: ?>
                                            <?php foreach ($ccEmails as $em): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-purple-50 text-purple-700 border border-purple-200/60">
                                                    <i class="fas fa-copy text-[9px] text-purple-400"></i> <?php echo htmlspecialchars($em); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <button type="button" onclick="toggleStatus(<?php echo $row['id']; ?>)" 
                                        class="status-btn inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold transition <?php echo $row['status'] === 'active' ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $row['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span>
                                        <span><?php echo ucfirst($row['status']); ?></span>
                                    </button>
                                </td>
                                <td class="py-3 px-4 text-slate-500 text-[11px] max-w-xs truncate">
                                    <?php echo htmlspecialchars($row['remarks'] ?? '—'); ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" onclick="editSetting(<?php echo $row['id']; ?>)" class="p-1.5 text-teal-600 hover:bg-teal-50 rounded-lg transition" title="Edit Setting">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button type="button" onclick="deleteSetting(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['target_name'])); ?>')" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete Setting">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 4: Sites by Zone (Weight 4 - Broadest) -->
        <div id="tab-content-zone" class="tab-panel p-4 hidden">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
                            <i class="fas fa-layer-group text-purple-500"></i>
                            <span>Sites by Zone Email Setting</span>
                        </h3>
                        <span class="px-2 py-0.5 rounded text-[10px] font-black bg-purple-100 text-purple-800 border border-purple-200">
                            Weight: 4 (Broadest Scope)
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">Emails configured here apply to all sites belonging to the specified operational zone</p>
                </div>
                <button type="button" onclick="openEmailModal('zone')" class="px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-700 rounded-lg text-xs font-bold transition flex items-center gap-1.5 border border-purple-200">
                    <i class="fas fa-plus"></i> Add Zone Email
                </button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-slate-200/80">
                <table class="w-full text-left border-collapse email-table" id="table-zone">
                    <thead>
                        <tr class="bg-slate-50/80 text-[11px] font-bold text-slate-600 border-b border-slate-200 uppercase tracking-wider">
                            <th class="py-3 px-4">Zone Name</th>
                            <th class="py-3 px-4">Coverage</th>
                            <th class="py-3 px-4">TO Recipient Emails</th>
                            <th class="py-3 px-4">CC Recipient Emails</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4">Remarks</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
                        <?php if (empty($zoneEmailSettings)): ?>
                            <tr class="empty-row">
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    <i class="fas fa-layer-group text-3xl text-slate-300 mb-2 block"></i>
                                    <p class="font-semibold text-slate-600">No zone-level email settings configured yet</p>
                                    <p class="text-xs text-slate-400 mt-1">Configure emails for entire zones (North, South, East, West, Central, Northeast).</p>
                                    <button type="button" onclick="openEmailModal('zone')" class="mt-3 px-3 py-1.5 bg-purple-600 text-white rounded-lg text-xs font-bold hover:bg-purple-500 transition">
                                        <i class="fas fa-plus mr-1"></i> Add Zone Email Setting
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($zoneEmailSettings as $row): 
                                $toEmails = SiteEmailSetting::parseEmails($row['to_emails']);
                                $ccEmails = SiteEmailSetting::parseEmails($row['cc_emails']);
                            ?>
                            <tr class="hover:bg-slate-50/80 transition setting-row" data-id="<?php echo $row['id']; ?>" data-type="zone">
                                <td class="py-3 px-4 font-bold text-purple-800 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5">
                                        <i class="fas fa-globe-asia text-purple-500"></i>
                                        <span class="target-name"><?php echo htmlspecialchars($row['target_name']); ?> Zone</span>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-500 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
                                        Zone Wide
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        <?php foreach ($toEmails as $em): ?>
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-200/60">
                                                <i class="fas fa-at text-[9px] text-blue-400"></i> <?php echo htmlspecialchars($em); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap gap-1 max-w-md">
                                        <?php if (empty($ccEmails)): ?>
                                            <span class="text-slate-400 italic text-[11px]">None</span>
                                        <?php else: ?>
                                            <?php foreach ($ccEmails as $em): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium bg-purple-50 text-purple-700 border border-purple-200/60">
                                                    <i class="fas fa-copy text-[9px] text-purple-400"></i> <?php echo htmlspecialchars($em); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center whitespace-nowrap">
                                    <button type="button" onclick="toggleStatus(<?php echo $row['id']; ?>)" 
                                        class="status-btn inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold transition <?php echo $row['status'] === 'active' ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                                        <span class="w-1.5 h-1.5 rounded-full <?php echo $row['status'] === 'active' ? 'bg-emerald-500' : 'bg-slate-400'; ?>"></span>
                                        <span><?php echo ucfirst($row['status']); ?></span>
                                    </button>
                                </td>
                                <td class="py-3 px-4 text-slate-500 text-[11px] max-w-xs truncate">
                                    <?php echo htmlspecialchars($row['remarks'] ?? '—'); ?>
                                </td>
                                <td class="py-3 px-4 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" onclick="editSetting(<?php echo $row['id']; ?>)" class="p-1.5 text-purple-600 hover:bg-purple-50 rounded-lg transition" title="Edit Setting">
                                            <i class="fas fa-edit text-xs"></i>
                                        </button>
                                        <button type="button" onclick="deleteSetting(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars(addslashes($row['target_name'])); ?>')" class="p-1.5 text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Delete Setting">
                                            <i class="fas fa-trash-alt text-xs"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab 5: Site Recipient Resolver / Tester -->
        <div id="tab-content-resolver" class="tab-panel p-4 hidden">
            <div class="bg-gradient-to-br from-indigo-50/60 via-slate-50 to-blue-50/40 p-5 rounded-2xl border border-indigo-100 mb-6">
                <div class="max-w-2xl">
                    <h3 class="text-sm font-extrabold text-slate-800 flex items-center gap-2">
                        <i class="fas fa-calculator text-indigo-600"></i>
                        <span>Live Site Recipient Resolution Engine</span>
                    </h3>
                    <p class="text-xs text-slate-600 mt-1">
                        Select any site to instantly calculate effective TO and CC recipients using the strict precedence: 
                        <strong class="text-indigo-700">Individual Site (Weight 1) &gt; City (Weight 2) &gt; State (Weight 3) &gt; Zone (Weight 4)</strong>.
                    </p>
                    
                    <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                        <div class="relative flex-1">
                            <select id="resolverSiteSelect" onchange="runResolver(this.value)" 
                                class="w-full pl-9 pr-8 py-2.5 bg-white border border-slate-300 rounded-xl text-xs font-semibold text-slate-800 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none cursor-pointer">
                                <option value="">-- Choose a Site to Test --</option>
                                <?php foreach ($allSites as $st): ?>
                                    <option value="<?php echo $st['id']; ?>">
                                        <?php echo htmlspecialchars($st['site_name']); ?> — <?php echo htmlspecialchars($st['city']); ?>, <?php echo htmlspecialchars($st['state']); ?> (<?php echo htmlspecialchars($st['bank_name'] ?: 'Site'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fas fa-map-marker-alt absolute left-3 top-3 text-xs text-indigo-500"></i>
                        </div>
                        <button type="button" onclick="runResolver(document.getElementById('resolverSiteSelect').value)"
                            class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md transition flex items-center justify-center gap-1.5 whitespace-nowrap">
                            <i class="fas fa-play text-xs"></i>
                            <span>Resolve Recipients</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resolution Results Container -->
            <div id="resolverResults" class="hidden space-y-6">
                <!-- Site Context Card -->
                <div class="bg-white rounded-xl p-4 border border-slate-200 shadow-sm flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-lg font-black">
                            <i class="fas fa-broadcast-tower"></i>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="text-base font-extrabold text-slate-900" id="res-site-name">XTPL0001</h4>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border" id="res-site-bank">Bank</span>
                            </div>
                            <div class="text-xs text-slate-500 flex items-center gap-3 mt-0.5">
                                <span><strong class="text-slate-700">City:</strong> <span id="res-site-city">Faridkot</span></span>
                                <span>&bull;</span>
                                <span><strong class="text-slate-700">State:</strong> <span id="res-site-state">Punjab</span></span>
                                <span>&bull;</span>
                                <span><strong class="text-slate-700">Zone:</strong> <span id="res-site-zone">North</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4 Cascading Hierarchy Cards Grid (Strict Weight Order: Site > City > State > Zone) -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- 1. Site Level (Weight 1 - Highest) -->
                    <div class="bg-white rounded-xl p-4 border border-indigo-200 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-2 h-full bg-indigo-500"></div>
                        <div class="text-[10px] uppercase font-bold text-indigo-600 flex items-center justify-between">
                            <span>1. Individual Site</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-indigo-100 text-indigo-800">Weight: 1</span>
                        </div>
                        <div class="flex items-center justify-between mt-0.5">
                            <h5 class="text-xs font-extrabold text-slate-800" id="title-level-site">Site Specific</h5>
                            <span id="badge-level-site" class="px-1.5 py-0.2 rounded text-[9px]"></span>
                        </div>
                        
                        <div class="mt-3 space-y-2">
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">TO Emails:</span>
                                <div id="res-level-site-to" class="text-xs mt-1 space-y-1"></div>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">CC Emails:</span>
                                <div id="res-level-site-cc" class="text-xs mt-1 space-y-1"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. City Level (Weight 2) -->
                    <div class="bg-white rounded-xl p-4 border border-cyan-200 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-2 h-full bg-cyan-500"></div>
                        <div class="text-[10px] uppercase font-bold text-cyan-600 flex items-center justify-between">
                            <span>2. City Level</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-cyan-100 text-cyan-800">Weight: 2</span>
                        </div>
                        <div class="flex items-center justify-between mt-0.5">
                            <h5 class="text-xs font-extrabold text-slate-800" id="title-level-city">City Level</h5>
                            <span id="badge-level-city" class="px-1.5 py-0.2 rounded text-[9px]"></span>
                        </div>
                        
                        <div class="mt-3 space-y-2">
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">TO Emails:</span>
                                <div id="res-level-city-to" class="text-xs mt-1 space-y-1"></div>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">CC Emails:</span>
                                <div id="res-level-city-cc" class="text-xs mt-1 space-y-1"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. State Level (Weight 3) -->
                    <div class="bg-white rounded-xl p-4 border border-teal-200 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-2 h-full bg-teal-500"></div>
                        <div class="text-[10px] uppercase font-bold text-teal-600 flex items-center justify-between">
                            <span>3. State Level</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-teal-100 text-teal-800">Weight: 3</span>
                        </div>
                        <div class="flex items-center justify-between mt-0.5">
                            <h5 class="text-xs font-extrabold text-slate-800" id="title-level-state">State Level</h5>
                            <span id="badge-level-state" class="px-1.5 py-0.2 rounded text-[9px]"></span>
                        </div>
                        
                        <div class="mt-3 space-y-2">
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">TO Emails:</span>
                                <div id="res-level-state-to" class="text-xs mt-1 space-y-1"></div>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">CC Emails:</span>
                                <div id="res-level-state-cc" class="text-xs mt-1 space-y-1"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Zone Level (Weight 4 - Broadest) -->
                    <div class="bg-white rounded-xl p-4 border border-purple-200 shadow-sm relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-2 h-full bg-purple-500"></div>
                        <div class="text-[10px] uppercase font-bold text-purple-600 flex items-center justify-between">
                            <span>4. Zone Level</span>
                            <span class="px-1.5 py-0.2 rounded text-[9px] font-extrabold bg-purple-100 text-purple-800">Weight: 4</span>
                        </div>
                        <div class="flex items-center justify-between mt-0.5">
                            <h5 class="text-xs font-extrabold text-slate-800" id="title-level-zone">Zone Level</h5>
                            <span id="badge-level-zone" class="px-1.5 py-0.2 rounded text-[9px]"></span>
                        </div>
                        
                        <div class="mt-3 space-y-2">
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">TO Emails:</span>
                                <div id="res-level-zone-to" class="text-xs mt-1 space-y-1"></div>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-slate-500 block uppercase">CC Emails:</span>
                                <div id="res-level-zone-cc" class="text-xs mt-1 space-y-1"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Combined Final Output Box -->
                <div class="bg-gradient-to-r from-slate-900 to-indigo-950 text-white rounded-2xl p-6 shadow-xl border border-indigo-900">
                    <div class="flex flex-wrap items-center justify-between pb-4 border-b border-white/10 mb-5 gap-3">
                        <div class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-xl bg-emerald-500/20 text-emerald-400 border border-emerald-400/30 flex items-center justify-center text-lg">
                                <i class="fas fa-paper-plane"></i>
                            </span>
                            <div>
                                <h4 class="text-base font-extrabold text-white">Consolidated Effective Delivery Payload</h4>
                                <p class="text-xs text-indigo-200/70">
                                    Resolved recipients ordered by priority: <span class="text-amber-300 font-semibold">Individual Site (1) &gt; City (2) &gt; State (3) &gt; Zone (4)</span>
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Final TO -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-extrabold uppercase text-blue-300 flex items-center gap-2">
                                    <i class="fas fa-inbox"></i> Final TO Recipients:
                                </span>
                                <button type="button" onclick="copyToClipboard('res-final-to-str')" class="text-[11px] text-blue-300 hover:text-white transition flex items-center gap-1">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            <div id="res-final-to-list" class="flex flex-wrap gap-1.5 min-h-[40px]"></div>
                            <input type="hidden" id="res-final-to-str">
                        </div>

                        <!-- Final CC -->
                        <div class="bg-white/5 rounded-xl p-4 border border-white/10">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-extrabold uppercase text-purple-300 flex items-center gap-2">
                                    <i class="fas fa-copy"></i> Final CC Recipients:
                                </span>
                                <button type="button" onclick="copyToClipboard('res-final-cc-str')" class="text-[11px] text-purple-300 hover:text-white transition flex items-center gap-1">
                                    <i class="fas fa-copy"></i> Copy
                                </button>
                            </div>
                            <div id="res-final-cc-list" class="flex flex-wrap gap-1.5 min-h-[40px]"></div>
                            <input type="hidden" id="res-final-cc-str">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty Resolver Placeholder -->
            <div id="resolverPlaceholder" class="p-12 text-center text-slate-400 border-2 border-dashed border-slate-200 rounded-2xl">
                <i class="fas fa-search-location text-4xl text-slate-300 mb-2 block"></i>
                <p class="font-bold text-slate-600">Select a site above to see resolved TO and CC email recipients</p>
                <p class="text-xs text-slate-400 mt-1">Tests the 4-tier weight inheritance engine (Individual &gt; City &gt; State &gt; Zone) in real-time.</p>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Modal (Ordered by Weight: Site > City > State > Zone) -->
<div id="emailModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" onclick="closeEmailModal()"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-2xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl w-full border border-slate-200">
            <!-- Modal Header -->
            <div class="bg-slate-900 text-white px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="w-9 h-9 rounded-lg bg-indigo-500/20 text-indigo-400 border border-indigo-400/30 flex items-center justify-center text-lg">
                        <i class="fas fa-envelope-open-text" id="modalIcon"></i>
                    </span>
                    <div>
                        <h3 class="text-sm font-extrabold text-white" id="modalTitle">Add Email Setting</h3>
                        <p class="text-[11px] text-slate-400">Configure TO and CC recipient routing</p>
                    </div>
                </div>
                <button type="button" onclick="closeEmailModal()" class="text-slate-400 hover:text-white transition p-1">
                    <i class="fas fa-times text-sm"></i>
                </button>
            </div>

            <!-- Form -->
            <form id="emailSettingForm" onsubmit="saveEmailSetting(event)" class="p-6 space-y-4">
                <input type="hidden" id="formSettingId" name="id" value="">
                <input type="hidden" id="formTargetId" name="target_id" value="">

                <!-- Level Selector (Ordered strictly by weight: Site > City > State > Zone) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                        1. Select Scope / Hierarchy Level <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2" id="levelSelectorGroup">
                        <label class="level-radio flex flex-col p-2 rounded-xl border border-slate-200 cursor-pointer transition hover:bg-slate-50">
                            <div class="flex items-center gap-1.5">
                                <input type="radio" name="type" value="site" checked onchange="onTypeChange(this.value)" class="text-indigo-600 focus:ring-indigo-500">
                                <span class="text-xs font-bold text-slate-800">1) Site</span>
                            </div>
                            <span class="text-[9px] font-extrabold text-indigo-600 mt-1">Weight 1 (Highest)</span>
                        </label>
                        <label class="level-radio flex flex-col p-2 rounded-xl border border-slate-200 cursor-pointer transition hover:bg-slate-50">
                            <div class="flex items-center gap-1.5">
                                <input type="radio" name="type" value="city" onchange="onTypeChange(this.value)" class="text-cyan-600 focus:ring-cyan-500">
                                <span class="text-xs font-bold text-slate-800">2) City</span>
                            </div>
                            <span class="text-[9px] font-extrabold text-cyan-600 mt-1">Weight 2</span>
                        </label>
                        <label class="level-radio flex flex-col p-2 rounded-xl border border-slate-200 cursor-pointer transition hover:bg-slate-50">
                            <div class="flex items-center gap-1.5">
                                <input type="radio" name="type" value="state" onchange="onTypeChange(this.value)" class="text-teal-600 focus:ring-teal-500">
                                <span class="text-xs font-bold text-slate-800">3) State</span>
                            </div>
                            <span class="text-[9px] font-extrabold text-teal-600 mt-1">Weight 3</span>
                        </label>
                        <label class="level-radio flex flex-col p-2 rounded-xl border border-slate-200 cursor-pointer transition hover:bg-slate-50">
                            <div class="flex items-center gap-1.5">
                                <input type="radio" name="type" value="zone" onchange="onTypeChange(this.value)" class="text-purple-600 focus:ring-purple-500">
                                <span class="text-xs font-bold text-slate-800">4) Zone</span>
                            </div>
                            <span class="text-[9px] font-extrabold text-purple-600 mt-1">Weight 4 (Broadest)</span>
                        </label>
                    </div>
                </div>

                <!-- Target Pickers (Dynamic depending on type) -->
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5" id="targetLabel">
                        2. Select Target Site (Weight 1 - Highest) <span class="text-rose-500">*</span>
                    </label>

                    <!-- Site Picker -->
                    <div id="picker-wrapper-site" class="target-picker">
                        <select id="select-site" onchange="onTargetSelectChange('site', this)" 
                            class="w-full px-3 py-2.5 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                            <option value="">-- Select an Individual Site --</option>
                            <?php foreach ($allSites as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['site_name']); ?>" data-id="<?php echo $st['id']; ?>">
                                    <?php echo htmlspecialchars($st['site_name']); ?> (<?php echo htmlspecialchars($st['city']); ?>, <?php echo htmlspecialchars($st['state']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- City Picker -->
                    <div id="picker-wrapper-city" class="target-picker hidden">
                        <select id="select-city" onchange="onTargetSelectChange('city', this)" 
                            class="w-full px-3 py-2.5 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-cyan-500 focus:bg-white outline-none">
                            <option value="">-- Select a City --</option>
                            <?php foreach ($allCities as $ct): ?>
                                <option value="<?php echo htmlspecialchars($ct['name']); ?>" data-id="<?php echo $ct['id'] ?? ''; ?>">
                                    <?php echo htmlspecialchars($ct['name']); ?><?php echo !empty($ct['state_name']) ? ' (' . htmlspecialchars($ct['state_name']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- State Picker -->
                    <div id="picker-wrapper-state" class="target-picker hidden">
                        <select id="select-state" onchange="onTargetSelectChange('state', this)" 
                            class="w-full px-3 py-2.5 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-teal-500 focus:bg-white outline-none">
                            <option value="">-- Select a State --</option>
                            <?php foreach ($allStates as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['name']); ?>" data-id="<?php echo $st['id'] ?? ''; ?>">
                                    <?php echo htmlspecialchars($st['name']); ?><?php echo !empty($st['zone_name']) ? ' (' . htmlspecialchars($st['zone_name']) . ' Zone)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Zone Picker -->
                    <div id="picker-wrapper-zone" class="target-picker hidden">
                        <select id="select-zone" onchange="onTargetSelectChange('zone', this)" 
                            class="w-full px-3 py-2.5 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-purple-500 focus:bg-white outline-none">
                            <option value="">-- Select an Operational Zone --</option>
                            <?php foreach ($allZones as $zn): ?>
                                <option value="<?php echo htmlspecialchars($zn['name']); ?>" data-id="<?php echo $zn['id']; ?>">
                                    <?php echo htmlspecialchars($zn['name']); ?> Zone
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Actual stored target_name input -->
                    <input type="hidden" id="formTargetName" name="target_name" value="">
                </div>

                <!-- TO Emails Input -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            3. TO Recipient Emails <span class="text-rose-500">*</span>
                        </label>
                        <span class="text-[10px] text-slate-400">Separate multiple with commas or newlines</span>
                    </div>
                    <textarea id="formToEmails" name="to_emails" rows="3" required
                        placeholder="e.g. engineer@advantagesb.com, manager@domain.com"
                        class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none font-mono"></textarea>
                    <div id="toValidationFeedback" class="text-[11px] text-rose-500 mt-1 hidden font-semibold"></div>
                </div>

                <!-- CC Emails Input -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                            4. CC Recipient Emails <span class="text-slate-400 font-normal">(Optional)</span>
                        </label>
                        <span class="text-[10px] text-slate-400">Separate multiple with commas or newlines</span>
                    </div>
                    <textarea id="formCcEmails" name="cc_emails" rows="2"
                        placeholder="e.g. audit@advantagesb.com, escalate@domain.com"
                        class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none font-mono"></textarea>
                    <div id="ccValidationFeedback" class="text-[11px] text-rose-500 mt-1 hidden font-semibold"></div>
                </div>

                <!-- Status & Remarks Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Status
                        </label>
                        <select id="formStatus" name="status" class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                            <option value="active">Active (Routing Enabled)</option>
                            <option value="inactive">Inactive (Routing Disabled)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                            Remarks / Notes
                        </label>
                        <input type="text" id="formRemarks" name="remarks" placeholder="Optional notes/purpose" 
                            class="w-full px-3 py-2 text-xs bg-slate-50 border border-slate-300 rounded-xl focus:ring-2 focus:ring-indigo-500 focus:bg-white outline-none">
                    </div>
                </div>

                <!-- Form Action Buttons -->
                <div class="pt-4 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" onclick="closeEmailModal()" 
                        class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition">
                        Cancel
                    </button>
                    <button type="submit" id="modalSaveBtn"
                        class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-bold shadow-md transition flex items-center gap-1.5">
                        <i class="fas fa-save"></i>
                        <span>Save Setting</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toastContainer" class="fixed bottom-5 right-5 z-50 flex flex-col gap-2 pointer-events-none"></div>

<script>
let currentActiveTab = 'site';

// Tab switching
function switchTab(tab) {
    currentActiveTab = tab;
    
    // Update tab button styles
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('bg-indigo-50', 'text-indigo-700', 'shadow-sm', 'border', 'border-indigo-200/60');
        btn.classList.add('text-slate-600');
    });

    const activeBtn = document.getElementById('tab-btn-' + tab);
    if (activeBtn) {
        activeBtn.classList.add('bg-indigo-50', 'text-indigo-700', 'shadow-sm', 'border', 'border-indigo-200/60');
        activeBtn.classList.remove('text-slate-600');
    }

    // Toggle panels
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    const activePanel = document.getElementById('tab-content-' + tab);
    if (activePanel) {
        activePanel.classList.remove('hidden');
    }

    // Show/hide search
    const searchWrapper = document.getElementById('tabSearchWrapper');
    if (searchWrapper) {
        if (tab === 'resolver') {
            searchWrapper.classList.add('hidden');
        } else {
            searchWrapper.classList.remove('hidden');
            const sInput = document.getElementById('tabSearchInput');
            if (sInput) sInput.value = '';
            filterTable('');
        }
    }
}

// Table search filter
function filterTable(query) {
    const q = query.toLowerCase().trim();
    const table = document.getElementById('table-' + currentActiveTab);
    if (!table) return;

    const rows = table.querySelectorAll('tbody tr.setting-row');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (!q || text.includes(q)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Type change in modal (Weights: site=1, city=2, state=3, zone=4)
function onTypeChange(type) {
    // Hide all pickers
    document.querySelectorAll('.target-picker').forEach(p => p.classList.add('hidden'));

    // Show selected picker
    const picker = document.getElementById('picker-wrapper-' + type);
    if (picker) picker.classList.remove('hidden');

    const labelMap = {
        'site': '2. Select Target Site (Weight 1 - Highest)',
        'city': '2. Select Target City (Weight 2)',
        'state': '2. Select Target State (Weight 3)',
        'zone': '2. Select Target Zone (Weight 4 - Broadest)'
    };
    document.getElementById('targetLabel').innerHTML = (labelMap[type] || '2. Select Target') + ' <span class="text-rose-500">*</span>';

    // Highlight selected radio parent
    document.querySelectorAll('.level-radio').forEach(r => {
        r.classList.remove('border-indigo-500', 'bg-indigo-50/50');
    });
    const selectedRadio = document.querySelector('input[name="type"][value="' + type + '"]');
    if (selectedRadio) {
        selectedRadio.closest('.level-radio').classList.add('border-indigo-500', 'bg-indigo-50/50');
    }

    // Update target name from currently selected option in picker
    const sel = document.getElementById('select-' + type);
    if (sel) {
        onTargetSelectChange(type, sel);
    }
}

function onTargetSelectChange(type, selectElem) {
    const opt = selectElem.options[selectElem.selectedIndex];
    if (opt && opt.value) {
        document.getElementById('formTargetName').value = opt.value;
        document.getElementById('formTargetId').value = opt.getAttribute('data-id') || '';
    } else {
        document.getElementById('formTargetName').value = '';
        document.getElementById('formTargetId').value = '';
    }
}

// Modal open/close
function openEmailModal(defaultType = null) {
    const type = defaultType || (currentActiveTab === 'resolver' ? 'site' : currentActiveTab);
    
    // Reset form
    document.getElementById('emailSettingForm').reset();
    document.getElementById('formSettingId').value = '';
    document.getElementById('formTargetId').value = '';
    document.getElementById('formTargetName').value = '';
    document.getElementById('modalTitle').textContent = 'Add Email Setting';
    document.getElementById('modalSaveBtn').innerHTML = '<i class="fas fa-save"></i> <span>Save Setting</span>';

    // Select type radio
    const radio = document.querySelector('input[name="type"][value="' + type + '"]');
    if (radio) {
        radio.checked = true;
    }
    onTypeChange(type);

    document.getElementById('emailModal').classList.remove('hidden');
}

function closeEmailModal() {
    document.getElementById('emailModal').classList.add('hidden');
}

// Edit setting
async function editSetting(id) {
    try {
        const resp = await fetch('api.php?action=get_setting&id=' + id);
        const res = await resp.json();
        if (!res.success) {
            showToast(res.error || 'Failed to fetch setting.', 'error');
            return;
        }

        const data = res.setting;
        document.getElementById('formSettingId').value = data.id;
        document.getElementById('modalTitle').textContent = 'Edit ' + capitalize(data.type) + ' Email Setting';
        document.getElementById('modalSaveBtn').innerHTML = '<i class="fas fa-save"></i> <span>Update Setting</span>';

        // Select type
        const radio = document.querySelector('input[name="type"][value="' + data.type + '"]');
        if (radio) radio.checked = true;
        onTypeChange(data.type);

        // Select target in dropdown
        const sel = document.getElementById('select-' + data.type);
        if (sel) {
            let matched = false;
            for (let i = 0; i < sel.options.length; i++) {
                if (sel.options[i].value.toLowerCase() === (data.target_name || '').toLowerCase() ||
                    (data.target_id && sel.options[i].getAttribute('data-id') == data.target_id)) {
                    sel.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
            if (!matched && data.target_name) {
                const newOpt = new Option(data.target_name, data.target_name, true, true);
                newOpt.setAttribute('data-id', data.target_id || '');
                sel.add(newOpt);
            }
        }

        document.getElementById('formTargetName').value = data.target_name;
        document.getElementById('formTargetId').value = data.target_id || '';
        document.getElementById('formToEmails').value = data.to_emails || '';
        document.getElementById('formCcEmails').value = data.cc_emails || '';
        document.getElementById('formStatus').value = data.status || 'active';
        document.getElementById('formRemarks').value = data.remarks || '';

        document.getElementById('emailModal').classList.remove('hidden');
    } catch (e) {
        showToast('Error loading setting details.', 'error');
    }
}

// Save setting via AJAX
async function saveEmailSetting(e) {
    e.preventDefault();

    const targetName = document.getElementById('formTargetName').value.trim();
    if (!targetName) {
        showToast('Please select a target from the dropdown.', 'warning');
        return;
    }

    const toEmails = document.getElementById('formToEmails').value.trim();
    if (!toEmails) {
        showToast('At least one TO email is required.', 'warning');
        return;
    }

    const form = document.getElementById('emailSettingForm');
    const formData = new FormData(form);
    formData.append('action', 'save');

    const saveBtn = document.getElementById('modalSaveBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving...</span>';

    try {
        const resp = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (!res.success) {
            showToast(res.error || 'Failed to save email setting.', 'error');
            saveBtn.disabled = false;
            saveBtn.innerHTML = originalText;
            return;
        }

        showToast(res.message, 'success');
        closeEmailModal();
        
        setTimeout(() => {
            window.location.reload();
        }, 600);
    } catch (err) {
        showToast('Network error while saving.', 'error');
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
    }
}

// Toggle active/inactive status
async function toggleStatus(id) {
    try {
        const formData = new FormData();
        formData.append('action', 'toggle_status');
        formData.append('id', id);

        const resp = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (!res.success) {
            showToast(res.error || 'Failed to update status.', 'error');
            return;
        }

        showToast(res.message, 'success');
        setTimeout(() => {
            window.location.reload();
        }, 500);
    } catch (e) {
        showToast('Network error.', 'error');
    }
}

// Delete setting
async function deleteSetting(id, name) {
    if (!confirm('Are you sure you want to delete the email setting for "' + name + '"?')) {
        return;
    }

    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);

        const resp = await fetch('api.php', {
            method: 'POST',
            body: formData
        });
        const res = await resp.json();

        if (!res.success) {
            showToast(res.error || 'Failed to delete setting.', 'error');
            return;
        }

        showToast(res.message, 'success');
        setTimeout(() => {
            window.location.reload();
        }, 500);
    } catch (e) {
        showToast('Network error.', 'error');
    }
}

// Run Site Recipient Resolver (Precedence: Site > City > State > Zone)
async function runResolver(siteId) {
    if (!siteId) {
        document.getElementById('resolverResults').classList.add('hidden');
        document.getElementById('resolverPlaceholder').classList.remove('hidden');
        return;
    }

    try {
        const resp = await fetch('api.php?action=resolve&site=' + encodeURIComponent(siteId));
        const res = await resp.json();

        if (!res.success) {
            showToast(res.error || 'Could not resolve site recipients.', 'error');
            return;
        }

        const data = res.resolution;
        document.getElementById('resolverPlaceholder').classList.add('hidden');
        document.getElementById('resolverResults').classList.remove('hidden');

        // Context header
        document.getElementById('res-site-name').textContent = data.site.site_name;
        document.getElementById('res-site-bank').textContent = data.site.bank_name || 'Site';
        document.getElementById('res-site-city').textContent = data.site.city || '—';
        document.getElementById('res-site-state').textContent = data.site.state || '—';
        document.getElementById('res-site-zone').textContent = data.site.zone ? (data.site.zone + ' Zone') : '—';

        // Render levels helper
        const renderLevelBox = (lvlKey, lvlData) => {
            const badge = document.getElementById('badge-level-' + lvlKey);
            const title = document.getElementById('title-level-' + lvlKey);
            const toBox = document.getElementById('res-level-' + lvlKey + '-to');
            const ccBox = document.getElementById('res-level-' + lvlKey + '-cc');

            title.textContent = lvlData.target || ('No ' + lvlKey);
            if (lvlData.configured) {
                badge.textContent = 'Configured';
                badge.className = 'px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800';
            } else {
                badge.textContent = 'Not Set';
                badge.className = 'px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-100 text-slate-500';
            }

            // TO emails
            if (lvlData.to && lvlData.to.length > 0) {
                toBox.innerHTML = lvlData.to.map(em => `
                    <div class="px-2 py-0.5 rounded text-[11px] bg-blue-50 text-blue-700 border border-blue-200/60 truncate" title="${em}">
                        <i class="fas fa-at text-[9px] text-blue-400 mr-1"></i>${em}
                    </div>
                `).join('');
            } else {
                toBox.innerHTML = '<span class="text-slate-400 italic text-[11px]">No TO emails</span>';
            }

            // CC emails
            if (lvlData.cc && lvlData.cc.length > 0) {
                ccBox.innerHTML = lvlData.cc.map(em => `
                    <div class="px-2 py-0.5 rounded text-[11px] bg-purple-50 text-purple-700 border border-purple-200/60 truncate" title="${em}">
                        <i class="fas fa-copy text-[9px] text-purple-400 mr-1"></i>${em}
                    </div>
                `).join('');
            } else {
                ccBox.innerHTML = '<span class="text-slate-400 italic text-[11px]">No CC emails</span>';
            }
        };

        // Render in exact weight order: Site > City > State > Zone
        renderLevelBox('site', data.levels.site);
        renderLevelBox('city', data.levels.city);
        renderLevelBox('state', data.levels.state);
        renderLevelBox('zone', data.levels.zone);

        // Render Consolidated Delivery Payload
        const eff = data.effective_recipients;
        const toList = document.getElementById('res-final-to-list');
        const ccList = document.getElementById('res-final-cc-list');

        document.getElementById('res-final-to-str').value = eff.to_string;
        document.getElementById('res-final-cc-str').value = eff.cc_string;

        if (eff.to && eff.to.length > 0) {
            toList.innerHTML = eff.to.map(em => `
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-blue-500/20 text-blue-200 border border-blue-400/40">
                    <i class="fas fa-check text-[9px] text-blue-400"></i> ${em}
                </span>
            `).join('');
        } else {
            toList.innerHTML = '<span class="text-slate-400 italic text-xs">No active TO recipients configured for this site hierarchy.</span>';
        }

        if (eff.cc && eff.cc.length > 0) {
            ccList.innerHTML = eff.cc.map(em => `
                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs font-semibold bg-purple-500/20 text-purple-200 border border-purple-400/40">
                    <i class="fas fa-copy text-[9px] text-purple-400"></i> ${em}
                </span>
            `).join('');
        } else {
            ccList.innerHTML = '<span class="text-slate-400 italic text-xs">No active CC recipients configured.</span>';
        }

    } catch (e) {
        showToast('Error resolving recipients.', 'error');
    }
}

// Copy to clipboard helper
function copyToClipboard(inputId) {
    const el = document.getElementById(inputId);
    if (!el || !el.value) {
        showToast('Nothing to copy.', 'warning');
        return;
    }
    navigator.clipboard.writeText(el.value).then(() => {
        showToast('Copied to clipboard!', 'success');
    }).catch(() => {
        showToast('Failed to copy.', 'error');
    });
}

// Toast notification helper
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    const colorMap = {
        'success': 'bg-emerald-600 text-white',
        'error': 'bg-rose-600 text-white',
        'warning': 'bg-amber-500 text-white',
        'info': 'bg-indigo-600 text-white'
    };
    const iconMap = {
        'success': 'fa-check-circle',
        'error': 'fa-exclamation-circle',
        'warning': 'fa-exclamation-triangle',
        'info': 'fa-info-circle'
    };

    toast.className = `${colorMap[type] || colorMap.info} px-4 py-3 rounded-xl shadow-xl flex items-center gap-2 text-xs font-bold pointer-events-auto transition-all duration-300 transform translate-y-2 opacity-0`;
    toast.innerHTML = `<i class="fas ${iconMap[type] || iconMap.info}"></i> <span>${message}</span>`;
    container.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    });

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 3500);
}

function capitalize(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : '';
}

document.addEventListener('DOMContentLoaded', () => {
    onTypeChange('site');
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

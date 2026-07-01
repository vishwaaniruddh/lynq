<?php
/**
 * Truncate Test Data Utility
 * 
 * Clears dummy/test records from database tables during testing phase.
 * Preserves: location masters (countries, states, zones, cities), 
 * users, roles, permissions, companies, and system configuration.
 * 
 * WARNING: This will permanently delete data! Use only in development/testing.
 */

require_once __DIR__ . '/../config/autoload.php';

// Only allow access for ADV admin users or CLI
$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    $sessionService = new SessionService();
    if (!$sessionService->isLoggedIn()) {
        header('Location: ../views/auth/login.php');
        exit;
    }
    
    // Check if user is ADV admin
    if (!isAdvUser() || !can('system.manage')) {
        $_SESSION['flash_error'] = 'Access denied. System admin permission required.';
        header('Location: ../dashboard.php');
        exit;
    }
}

class TruncateTestData {
    private $db;
    
    // Tables to PRESERVE (will NOT be truncated)
    private $preserveTables = [
        // Location masters
        'countries',
        'states', 
        'zones',
        'cities',
        
        // User & auth system
        'users',
        'roles',
        'permissions',
        'role_permissions',
        'user_permissions',
        'user_roles',
        
        // Company data
        'companies',
        'company_permissions',
        
        // System config
        'settings',
        'migrations',
        
        // Session data (optional - can be cleared separately)
        'sessions',
        'login_attempts',
        'ip_restrictions',
    ];
    
    // Tables to TRUNCATE (test/transactional data)
    private $truncateTables = [
        // Inventory data
        'products',
        'product_categories',
        'warehouses',
        'warehouse_stock',
        'stock_entries',
        'stock_entry_items',
        'dispatches',
        'dispatch_items',
        'transfers',
        'transfer_items',
        'assets',
        'asset_history',
        'repairs',
        'repair_items',
        'inventory_audit_log',
        
        // Site & delegation data
        'sites',
        'site_delegations',
        'site_assignments',
        'delegation_history',
        
        // Master data (except locations)
        'banks',
        'customers',
        'couriers',
        
        // Audit logs
        'user_audit_log',
        'api_access_log',
    ];
    
    public function __construct() {
        $this->db = DatabaseConfig::getInstance();
    }
    
    /**
     * Get list of tables that will be truncated
     */
    public function getTablesToTruncate(): array {
        return $this->truncateTables;
    }
    
    /**
     * Get list of tables that will be preserved
     */
    public function getPreservedTables(): array {
        return $this->preserveTables;
    }
    
    /**
     * Check if a table exists in the database
     */
    private function tableExists(string $table): bool {
        // Sanitize table name (only allow alphanumeric and underscore)
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sql = "SHOW TABLES LIKE '$table'";
        $result = $this->db->getResults($sql, [], '');
        return !empty($result);
    }
    
    /**
     * Get row count for a table
     */
    public function getRowCount(string $table): int {
        if (!$this->tableExists($table)) {
            return 0;
        }
        // Sanitize table name
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $sql = "SELECT COUNT(*) as count FROM `$table`";
        $result = $this->db->getResults($sql, [], '');
        return $result[0]['count'] ?? 0;
    }
    
    /**
     * Truncate a single table
     */
    private function truncateTable(string $table): array {
        if (!$this->tableExists($table)) {
            return ['success' => false, 'message' => "Table '$table' does not exist", 'rows' => 0];
        }
        
        $rowCount = $this->getRowCount($table);
        
        try {
            // Disable foreign key checks temporarily
            $this->db->executeQuery("SET FOREIGN_KEY_CHECKS = 0", [], '');
            
            $sql = "TRUNCATE TABLE `$table`";
            $stmt = $this->db->executeQuery($sql, [], '');
            $stmt->close();
            
            // Re-enable foreign key checks
            $this->db->executeQuery("SET FOREIGN_KEY_CHECKS = 1", [], '');
            
            return ['success' => true, 'message' => "Truncated '$table'", 'rows' => $rowCount];
        } catch (Exception $e) {
            // Re-enable foreign key checks even on error
            $this->db->executeQuery("SET FOREIGN_KEY_CHECKS = 1", [], '');
            return ['success' => false, 'message' => "Error truncating '$table': " . $e->getMessage(), 'rows' => 0];
        }
    }
    
    /**
     * Truncate all test data tables
     */
    public function truncateAll(): array {
        $results = [];
        $totalRows = 0;
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($this->truncateTables as $table) {
            $result = $this->truncateTable($table);
            $results[$table] = $result;
            
            if ($result['success']) {
                $totalRows += $result['rows'];
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        return [
            'results' => $results,
            'summary' => [
                'tables_processed' => count($this->truncateTables),
                'tables_truncated' => $successCount,
                'tables_failed' => $errorCount,
                'total_rows_deleted' => $totalRows
            ]
        ];
    }
    
    /**
     * Truncate specific tables only
     */
    public function truncateSelected(array $tables): array {
        $results = [];
        $totalRows = 0;
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($tables as $table) {
            // Only allow truncating tables in the allowed list
            if (!in_array($table, $this->truncateTables)) {
                $results[$table] = ['success' => false, 'message' => "Table '$table' is not allowed to be truncated", 'rows' => 0];
                $errorCount++;
                continue;
            }
            
            $result = $this->truncateTable($table);
            $results[$table] = $result;
            
            if ($result['success']) {
                $totalRows += $result['rows'];
                $successCount++;
            } else {
                $errorCount++;
            }
        }
        
        return [
            'results' => $results,
            'summary' => [
                'tables_processed' => count($tables),
                'tables_truncated' => $successCount,
                'tables_failed' => $errorCount,
                'total_rows_deleted' => $totalRows
            ]
        ];
    }
    
    /**
     * Get current row counts for all truncatable tables
     */
    public function getTableStats(): array {
        $stats = [];
        foreach ($this->truncateTables as $table) {
            $stats[$table] = [
                'exists' => $this->tableExists($table),
                'rows' => $this->getRowCount($table)
            ];
        }
        return $stats;
    }
}

// Handle CLI execution
if ($isCli) {
    echo "=== Truncate Test Data Utility ===\n\n";
    
    $truncator = new TruncateTestData();
    
    $action = $argv[1] ?? 'stats';
    
    switch ($action) {
        case 'stats':
            echo "Current table statistics:\n";
            echo str_repeat('-', 50) . "\n";
            $stats = $truncator->getTableStats();
            $totalRows = 0;
            foreach ($stats as $table => $info) {
                if ($info['exists']) {
                    echo sprintf("%-35s %8d rows\n", $table, $info['rows']);
                    $totalRows += $info['rows'];
                } else {
                    echo sprintf("%-35s (not found)\n", $table);
                }
            }
            echo str_repeat('-', 50) . "\n";
            echo sprintf("%-35s %8d rows\n", "TOTAL", $totalRows);
            break;
            
        case 'truncate':
            echo "WARNING: This will delete all test data!\n";
            echo "Tables to be truncated:\n";
            foreach ($truncator->getTablesToTruncate() as $table) {
                echo "  - $table\n";
            }
            echo "\nPreserved tables (NOT truncated):\n";
            foreach ($truncator->getPreservedTables() as $table) {
                echo "  - $table\n";
            }
            echo "\nType 'yes' to confirm: ";
            $confirm = trim(fgets(STDIN));
            
            if ($confirm === 'yes') {
                echo "\nTruncating tables...\n";
                $result = $truncator->truncateAll();
                
                foreach ($result['results'] as $table => $info) {
                    $status = $info['success'] ? '✓' : '✗';
                    echo "  $status $table: {$info['message']} ({$info['rows']} rows)\n";
                }
                
                echo "\n=== Summary ===\n";
                echo "Tables processed: {$result['summary']['tables_processed']}\n";
                echo "Tables truncated: {$result['summary']['tables_truncated']}\n";
                echo "Tables failed: {$result['summary']['tables_failed']}\n";
                echo "Total rows deleted: {$result['summary']['total_rows_deleted']}\n";
            } else {
                echo "Aborted.\n";
            }
            break;
            
        default:
            echo "Usage: php truncate_test_data.php [stats|truncate]\n";
            echo "  stats    - Show current row counts\n";
            echo "  truncate - Truncate all test data tables\n";
    }
    
    exit;
}

// Web interface
$truncator = new TruncateTestData();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'truncate_all') {
        $result = $truncator->truncateAll();
        $message = "Truncated {$result['summary']['tables_truncated']} tables, deleted {$result['summary']['total_rows_deleted']} rows.";
        $messageType = $result['summary']['tables_failed'] > 0 ? 'warning' : 'success';
    } elseif ($_POST['action'] === 'truncate_selected' && isset($_POST['tables'])) {
        $result = $truncator->truncateSelected($_POST['tables']);
        $message = "Truncated {$result['summary']['tables_truncated']} tables, deleted {$result['summary']['total_rows_deleted']} rows.";
        $messageType = $result['summary']['tables_failed'] > 0 ? 'warning' : 'success';
    }
}

$stats = $truncator->getTableStats();
$currentUser = $sessionService->getCurrentUser();
$baseUrl = '..';
$pageTitle = 'Truncate Test Data';
$currentPage = 'admin_truncate';
$isLoggedIn = true;
$breadcrumbs = [
    ['label' => 'Dashboard', 'url' => '../dashboard.php'],
    ['label' => 'Admin'],
    ['label' => 'Truncate Test Data']
];

ob_start();
?>

<div class="bg-white rounded-xl shadow-sm">
    <div class="p-6 border-b">
        <div class="flex items-center gap-3">
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                <i class="fas fa-trash-alt text-red-500 text-xl"></i>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Truncate Test Data</h3>
                <p class="text-sm text-gray-500">Clear dummy records from database tables during testing phase</p>
            </div>
        </div>
    </div>
    
    <?php if ($message): ?>
    <div class="p-4 mx-6 mt-4 rounded-lg <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
        <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> mr-2"></i>
        <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>
    
    <div class="p-6">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <div class="flex items-start">
                <i class="fas fa-exclamation-triangle text-red-500 mt-1 mr-3"></i>
                <div>
                    <h4 class="font-semibold text-red-700">Warning</h4>
                    <p class="text-sm text-red-600">This action will permanently delete data. Only use during development/testing phase.</p>
                </div>
            </div>
        </div>
        
        <form method="POST" onsubmit="return confirm('Are you sure you want to truncate the selected tables? This cannot be undone!');">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Tables to Truncate -->
                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">
                        <i class="fas fa-database mr-2 text-red-500"></i>Tables to Truncate
                    </h4>
                    <div class="space-y-2 max-h-96 overflow-y-auto border rounded-lg p-3">
                        <?php foreach ($stats as $table => $info): ?>
                        <label class="flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer">
                            <div class="flex items-center">
                                <input type="checkbox" name="tables[]" value="<?php echo $table; ?>" 
                                    class="w-4 h-4 text-red-500 rounded mr-3" <?php echo $info['exists'] ? '' : 'disabled'; ?>>
                                <span class="<?php echo $info['exists'] ? 'text-gray-700' : 'text-gray-400'; ?>">
                                    <?php echo $table; ?>
                                </span>
                            </div>
                            <span class="text-sm <?php echo $info['rows'] > 0 ? 'text-blue-600 font-medium' : 'text-gray-400'; ?>">
                                <?php echo $info['exists'] ? number_format($info['rows']) . ' rows' : 'N/A'; ?>
                            </span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Preserved Tables -->
                <div>
                    <h4 class="font-semibold text-gray-700 mb-3">
                        <i class="fas fa-shield-alt mr-2 text-green-500"></i>Preserved Tables (Protected)
                    </h4>
                    <div class="space-y-2 max-h-96 overflow-y-auto border rounded-lg p-3 bg-green-50">
                        <?php foreach ($truncator->getPreservedTables() as $table): ?>
                        <div class="flex items-center p-2">
                            <i class="fas fa-lock text-green-500 mr-3"></i>
                            <span class="text-gray-700"><?php echo $table; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="flex gap-3 mt-6 pt-6 border-t">
                <button type="submit" name="action" value="truncate_selected" 
                    class="px-4 py-2 bg-orange-500 text-white rounded-lg hover:bg-orange-600 transition">
                    <i class="fas fa-trash mr-2"></i>Truncate Selected
                </button>
                <button type="submit" name="action" value="truncate_all" 
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                    <i class="fas fa-bomb mr-2"></i>Truncate All Test Data
                </button>
                <a href="../dashboard.php" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../views/layouts/base.php';
?>

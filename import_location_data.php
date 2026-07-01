<?php
/**
 * Location Data Import Script
 * Imports location master data (zones, countries, states, cities) from template SQL files
 * 
 * Requirements: 3.1, 3.2, 3.3, 3.4
 * - 3.1: Truncate existing data in countries, zones, states, and cities tables
 * - 3.2: Insert all records from template SQL files in correct order
 * - 3.3: Display summary of imported records count for each table
 * - 3.4: Rollback all changes and display error message on failure
 */

require_once __DIR__ . '/config/database.php';

class LocationDataImporter {
    private $db;
    private $templatePath;
    private $results = [];
    private $errors = [];
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->db = DatabaseConfig::getInstance()->getConnection();
        // Template SQL files are located in clarity/template/data/
        $this->templatePath = realpath(__DIR__ . '/../template/data');
    }
    
    /**
     * Run the import process
     * 
     * @return array Import results with counts and any errors
     */
    public function import(): array {
        $this->results = [
            'success' => false,
            'zones' => 0,
            'countries' => 0,
            'states' => 0,
            'cities' => 0,
            'errors' => [],
            'message' => ''
        ];
        
        // Verify template path exists
        if (!$this->templatePath || !is_dir($this->templatePath)) {
            $this->results['errors'][] = "Template directory not found: " . ($this->templatePath ?: 'null');
            $this->results['message'] = "Import failed: Template directory not found";
            return $this->results;
        }
        
        // Verify all required SQL files exist
        $requiredFiles = ['zones.sql', 'countries.sql', 'states.sql', 'cities.sql'];
        foreach ($requiredFiles as $file) {
            $filePath = $this->templatePath . DIRECTORY_SEPARATOR . $file;
            if (!file_exists($filePath)) {
                $this->results['errors'][] = "Import file not found: $file";
                $this->results['message'] = "Import failed: Required SQL file not found";
                return $this->results;
            }
        }
        
        try {
            // Start transaction
            $this->db->begin_transaction();
            
            // Disable foreign key checks temporarily for truncation
            $this->db->query("SET FOREIGN_KEY_CHECKS = 0");
            
            // Step 1: Truncate tables (Requirements: 3.1)
            $this->truncateTables();
            
            // Re-enable foreign key checks
            $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
            
            // Step 2: Import data in correct order (Requirements: 3.2)
            // Order: zones -> countries -> states -> cities (due to foreign key dependencies)
            $this->results['zones'] = $this->importFromFile('zones.sql', 'zones');
            $this->results['countries'] = $this->importFromFile('countries.sql', 'countries');
            $this->results['states'] = $this->importFromFile('states.sql', 'states');
            $this->results['cities'] = $this->importFromFile('cities.sql', 'cities');
            
            // Commit transaction
            $this->db->commit();
            
            $this->results['success'] = true;
            $this->results['message'] = "Import completed successfully";
            
        } catch (Exception $e) {
            // Rollback on error (Requirements: 3.4)
            $this->db->rollback();
            $this->db->query("SET FOREIGN_KEY_CHECKS = 1");
            
            $this->results['success'] = false;
            $this->results['errors'][] = $e->getMessage();
            $this->results['message'] = "Import failed: " . $e->getMessage();
        }
        
        return $this->results;
    }

    
    /**
     * Truncate all location tables
     * Requirements: 3.1
     * 
     * @throws Exception If truncation fails
     */
    private function truncateTables(): void {
        $tables = ['cities', 'states', 'countries', 'zones'];
        
        foreach ($tables as $table) {
            $result = $this->db->query("TRUNCATE TABLE `$table`");
            if ($result === false) {
                throw new Exception("Failed to truncate table: $table - " . $this->db->error);
            }
        }
    }
    
    /**
     * Import data from a SQL file
     * Requirements: 3.2
     * 
     * @param string $filename SQL filename
     * @param string $tableName Table name for verification
     * @return int Number of records imported
     * @throws Exception If import fails
     */
    private function importFromFile(string $filename, string $tableName): int {
        $filePath = $this->templatePath . DIRECTORY_SEPARATOR . $filename;
        
        if (!file_exists($filePath)) {
            throw new Exception("Import file not found: $filename");
        }
        
        $sqlContent = file_get_contents($filePath);
        if ($sqlContent === false) {
            throw new Exception("Failed to read file: $filename");
        }
        
        // Extract INSERT statements from the SQL file
        $insertStatements = $this->extractInsertStatements($sqlContent, $tableName);
        
        if (empty($insertStatements)) {
            throw new Exception("No INSERT statements found in file: $filename");
        }
        
        $importedCount = 0;
        
        foreach ($insertStatements as $sql) {
            $result = $this->db->query($sql);
            if ($result === false) {
                throw new Exception("Failed to execute INSERT for $tableName: " . $this->db->error);
            }
            $importedCount += $this->db->affected_rows;
        }
        
        return $importedCount;
    }
    
    /**
     * Extract INSERT statements from SQL content
     * 
     * @param string $sqlContent Full SQL file content
     * @param string $tableName Table name to filter for
     * @return array Array of INSERT statements
     */
    private function extractInsertStatements(string $sqlContent, string $tableName): array {
        $statements = [];
        
        // Match INSERT INTO statements for the specific table
        // Pattern handles multi-line INSERT statements with VALUES
        $pattern = '/INSERT\s+INTO\s+[`\']?' . preg_quote($tableName, '/') . '[`\']?\s*\([^)]+\)\s*VALUES\s*([^;]+);/is';
        
        if (preg_match_all($pattern, $sqlContent, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $statements[] = $match[0];
            }
        }
        
        return $statements;
    }
    
    /**
     * Get the count of records in a table
     * 
     * @param string $tableName Table name
     * @return int Record count
     */
    public function getTableCount(string $tableName): int {
        $result = $this->db->query("SELECT COUNT(*) as count FROM `$tableName`");
        if ($result) {
            $row = $result->fetch_assoc();
            return (int)($row['count'] ?? 0);
        }
        return 0;
    }
}

// ==================== CLI / Web Interface ====================

// Check if running from CLI or web
$isCli = php_sapi_name() === 'cli';

if ($isCli) {
    // CLI execution
    echo "===========================================\n";
    echo "  Location Data Import Script\n";
    echo "===========================================\n\n";
    
    $importer = new LocationDataImporter();
    $results = $importer->import();
    
    if ($results['success']) {
        echo "✓ Import completed successfully!\n\n";
        echo "Summary of imported records:\n";
        echo "-------------------------------------------\n";
        echo "  Zones:     " . str_pad($results['zones'], 6, ' ', STR_PAD_LEFT) . " records\n";
        echo "  Countries: " . str_pad($results['countries'], 6, ' ', STR_PAD_LEFT) . " records\n";
        echo "  States:    " . str_pad($results['states'], 6, ' ', STR_PAD_LEFT) . " records\n";
        echo "  Cities:    " . str_pad($results['cities'], 6, ' ', STR_PAD_LEFT) . " records\n";
        echo "-------------------------------------------\n";
        $total = $results['zones'] + $results['countries'] + $results['states'] + $results['cities'];
        echo "  Total:     " . str_pad($total, 6, ' ', STR_PAD_LEFT) . " records\n";
        echo "===========================================\n";
    } else {
        echo "✗ Import failed!\n\n";
        echo "Error: " . $results['message'] . "\n";
        if (!empty($results['errors'])) {
            echo "\nDetails:\n";
            foreach ($results['errors'] as $error) {
                echo "  - $error\n";
            }
        }
        echo "===========================================\n";
        exit(1);
    }
} else {
    // Web execution - require authentication
    require_once __DIR__ . '/config/autoload.php';
    
    // Use SessionService for proper authentication
    $sessionService = new SessionService();
    
    if (!$sessionService->isLoggedIn()) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Access denied. Please log in first.'
            ]);
            exit;
        }
        header('Location: views/auth/login.php');
        exit;
    }
    
    // Get current user data including company_type from database
    $currentUser = $sessionService->getCurrentUser();
    $isAdv = $currentUser && isset($currentUser['company_type']) && $currentUser['company_type'] === 'ADV';
    
    if (!$isAdv) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Access denied. This operation requires ADV administrator privileges.'
            ]);
            exit;
        }
        header('Location: dashboard.php?error=access_denied');
        exit;
    }
    
    // Handle AJAX request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        
        $importer = new LocationDataImporter();
        $results = $importer->import();
        
        echo json_encode($results);
        exit;
    }
    
    // Display web interface - use the base layout system
    $pageTitle = 'Import Location Data';
    $baseUrl = '.';
    $currentPage = 'import_location';
    $isLoggedIn = true;
    $breadcrumbs = [
        ['label' => 'Dashboard', 'url' => 'dashboard.php'],
        ['label' => 'Import Location Data']
    ];
    
    // Capture page content
    ob_start();
    ?>
    
    <div class="bg-white rounded-xl shadow-sm">
        <!-- Header -->
        <div class="p-6 border-b">
            <h3 class="text-lg font-semibold text-gray-800">
                <i class="fas fa-database mr-2 text-primary"></i>Import Location Master Data
            </h3>
            <p class="text-sm text-gray-500 mt-1">Import zones, countries, states, and cities from template SQL files</p>
        </div>
        
        <div class="p-6">
            <!-- Warning -->
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mt-0.5 mr-3"></i>
                    <div>
                        <h4 class="font-medium text-yellow-800">Warning</h4>
                        <p class="text-sm text-yellow-700 mt-1">
                            This operation will delete all existing location data (zones, countries, states, cities) 
                            and replace it with data from the template files.
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Status Message -->
            <div id="import-status" class="hidden mb-6">
                <div id="status-alert" class="rounded-lg p-4">
                    <span id="status-message"></span>
                </div>
            </div>
            
            <!-- Import Summary -->
            <div id="import-summary" class="hidden mb-6">
                <h4 class="font-medium text-gray-800 mb-3">Import Summary</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Table</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Records Imported</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-700">Zones</td>
                                <td class="px-4 py-3 text-sm text-gray-900" id="count-zones">-</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-700">Countries</td>
                                <td class="px-4 py-3 text-sm text-gray-900" id="count-countries">-</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-700">States</td>
                                <td class="px-4 py-3 text-sm text-gray-900" id="count-states">-</td>
                            </tr>
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-700">Cities</td>
                                <td class="px-4 py-3 text-sm text-gray-900" id="count-cities">-</td>
                            </tr>
                            <tr class="bg-primary/5">
                                <td class="px-4 py-3 text-sm font-semibold text-gray-800">Total</td>
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900" id="count-total">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex gap-3">
                <button type="button" id="btn-import" 
                        class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-blue-600 transition flex items-center">
                    <i class="fas fa-upload mr-2"></i>Start Import
                </button>
                <button type="button" id="btn-importing" 
                        class="px-4 py-2 bg-primary text-white rounded-lg opacity-75 cursor-not-allowed hidden flex items-center" disabled>
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    Importing...
                </button>
            </div>
        </div>
    </div>
    
    <script>
    document.getElementById('btn-import').addEventListener('click', function() {
        if (!confirm('Are you sure you want to import location data? This will delete all existing location records.')) {
            return;
        }
        
        const btnImport = document.getElementById('btn-import');
        const btnImporting = document.getElementById('btn-importing');
        const statusDiv = document.getElementById('import-status');
        const statusAlert = document.getElementById('status-alert');
        const statusMessage = document.getElementById('status-message');
        const summaryDiv = document.getElementById('import-summary');
        
        // Show loading state
        btnImport.classList.add('hidden');
        btnImporting.classList.remove('hidden');
        statusDiv.classList.add('hidden');
        summaryDiv.classList.add('hidden');
        
        fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            btnImport.classList.remove('hidden');
            btnImporting.classList.add('hidden');
            statusDiv.classList.remove('hidden');
            
            if (data.success) {
                statusAlert.className = 'bg-green-50 border border-green-200 rounded-lg p-4';
                statusMessage.innerHTML = '<i class="fas fa-check-circle text-green-500 mr-2"></i><span class="text-green-800">' + data.message + '</span>';
                
                // Show summary
                summaryDiv.classList.remove('hidden');
                document.getElementById('count-zones').textContent = data.zones;
                document.getElementById('count-countries').textContent = data.countries;
                document.getElementById('count-states').textContent = data.states;
                document.getElementById('count-cities').textContent = data.cities;
                document.getElementById('count-total').textContent = (data.zones + data.countries + data.states + data.cities);
            } else {
                statusAlert.className = 'bg-red-50 border border-red-200 rounded-lg p-4';
                let errorHtml = '<i class="fas fa-times-circle text-red-500 mr-2"></i><span class="text-red-800">' + data.message + '</span>';
                
                if (data.errors && data.errors.length > 0) {
                    errorHtml += '<ul class="mt-2 ml-6 list-disc text-sm text-red-700">';
                    data.errors.forEach(function(error) {
                        errorHtml += '<li>' + error + '</li>';
                    });
                    errorHtml += '</ul>';
                }
                statusMessage.innerHTML = errorHtml;
            }
        })
        .catch(error => {
            btnImport.classList.remove('hidden');
            btnImporting.classList.add('hidden');
            statusDiv.classList.remove('hidden');
            statusAlert.className = 'bg-red-50 border border-red-200 rounded-lg p-4';
            statusMessage.innerHTML = '<i class="fas fa-times-circle text-red-500 mr-2"></i><span class="text-red-800">An error occurred: ' + error.message + '</span>';
        });
    });
    </script>
    
    <?php
    $content = ob_get_clean();
    require_once __DIR__ . '/views/layouts/base.php';
}

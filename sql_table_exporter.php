<?php
// Enable strict error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

$remote_config = [
    'host' => 'sql109.infinityfree.com',
    'user' => 'if0_40845939',
    'pass' => 'AVav2026',
    'name' => 'if0_40845939_clarity_db',
    'port' => 3306
];

$is_cli = (php_sapi_name() === 'cli');

// Basic Security Auth (bypass on localhost)
$is_authorized = false;
if ($is_cli) {
    $is_authorized = true;
} else {
    if (isset($_POST['action']) && $_POST['action'] === 'login') {
        $password = $_POST['password'] ?? '';
        if ($password === $remote_config['pass']) {
            $_SESSION['db_sync_auth'] = true;
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Invalid Security Key']);
            exit;
        }
    }
    
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        unset($_SESSION['db_sync_auth']);
        header('Location: ' . basename($_SERVER['PHP_SELF']));
        exit;
    }
    
    $is_authorized = isset($_SESSION['db_sync_auth']) && $_SESSION['db_sync_auth'] === true;
    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        $is_authorized = true;
    }
}

// Database Connection Helper
function getDbConnection() {
    require_once __DIR__ . '/config/database.php';
    return Database::getInstance()->getConnection();
}

// Export Table Logic
function exportTable($pdo, $table, $export_dir) {
    $sql = "-- Database Table Export for `$table`\n";
    $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $sql .= "-- DB Name: if0_40845939_clarity_db\n\n";
    
    $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
    $sql .= "DROP TABLE IF EXISTS `$table`;\n\n";
    
    // Structure
    $create_stmt = $pdo->query("SHOW CREATE TABLE `$table`");
    $create_row = $create_stmt->fetch();
    $create_sql = $create_row['Create Table'] ?? '';
    
    if (empty($create_sql)) {
        throw new Exception("Could not fetch structure for table `$table`.");
    }
    
    $sql .= $create_sql . ";\n\n";
    
    // Data
    $data_stmt = $pdo->query("SELECT * FROM `$table`");
    $rows = $data_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        $sql .= "-- Dumping data for table `$table`\n";
        foreach ($rows as $row) {
            $cols = array_map(function($c) { return "`$c`"; }, array_keys($row));
            $vals = array_map(function($v) use ($pdo) {
                if ($v === null) return 'NULL';
                return $pdo->quote($v);
            }, array_values($row));
            
            $sql .= "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
        }
    } else {
        $sql .= "-- No data found in `$table`\n";
    }
    
    $sql .= "\nSET FOREIGN_KEY_CHECKS = 1;\n";
    
    $file_path = $export_dir . '/' . $table . '.sql';
    file_put_contents($file_path, $sql);
    
    return [
        'rows' => count($rows),
        'file' => $table . '.sql',
        'size' => filesize($file_path)
    ];
}

// Run Export Task
function runExport() {
    try {
        $pdo = getDbConnection();
        $export_dir = __DIR__ . '/tables';
        
        if (!is_dir($export_dir)) {
            if (!mkdir($export_dir, 0755, true)) {
                throw new Exception("Failed to create tables directory: $export_dir");
            }
        }
        
        // Fetch all tables
        $tables_stmt = $pdo->query("SHOW TABLES");
        $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $results = [];
        foreach ($tables as $table) {
            $results[$table] = exportTable($pdo, $table, $export_dir);
        }
        
        return [
            'success' => true,
            'tables' => $results,
            'count' => count($results)
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Zip Generation Helper
function generateZip() {
    $export_dir = __DIR__ . '/tables';
    if (!is_dir($export_dir)) {
        return ['success' => false, 'error' => 'No exported files found. Please export first.'];
    }
    
    if (!class_exists('ZipArchive')) {
        return ['success' => false, 'error' => 'ZipArchive class is not enabled in your PHP installation.'];
    }
    
    $zip_file = __DIR__ . '/tables_export.zip';
    if (file_exists($zip_file)) {
        unlink($zip_file);
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zip_file, ZipArchive::CREATE) !== true) {
        return ['success' => false, 'error' => 'Could not create ZIP archive file.'];
    }
    
    $files = scandir($export_dir);
    $added = 0;
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $zip->addFile($export_dir . '/' . $file, $file);
            $added++;
        }
    }
    
    $zip->close();
    
    if ($added === 0) {
        unlink($zip_file);
        return ['success' => false, 'error' => 'No SQL files found to compress.'];
    }
    
    return [
        'success' => true,
        'file' => 'tables_export.zip',
        'size' => filesize($zip_file),
        'count' => $added
    ];
}

// Handle CLI Trigger
if ($is_cli) {
    echo "==========================================\n";
    echo " MySQL Table SQL Exporter (CLI Mode)\n";
    echo "==========================================\n\n";
    
    echo "Scanning database and exporting tables...\n";
    $result = runExport();
    
    if ($result['success']) {
        echo "Export Completed Successfully!\n";
        echo "Destination: " . __DIR__ . "/tables/\n\n";
        
        foreach ($result['tables'] as $table => $info) {
            $size_kb = round($info['size'] / 1024, 2);
            echo "  [+] {$table} -> {$info['file']} ({$info['rows']} rows, {$size_kb} KB)\n";
        }
        
        echo "\nCreating ZIP archive...\n";
        $zip_res = generateZip();
        if ($zip_res['success']) {
            $zip_size_mb = round($zip_res['size'] / 1024 / 1024, 2);
            echo "  [+] Created {$zip_res['file']} ({$zip_size_mb} MB, {$zip_res['count']} files)\n";
        } else {
            echo "  [-] Could not create ZIP: {$zip_res['error']}\n";
        }
        exit(0);
    } else {
        echo "Export Failed: " . $result['error'] . "\n";
        exit(1);
    }
}

// Handle AJAX actions
if (isset($_POST['action']) || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    if (!$is_authorized && $action !== 'login') {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    // Download zip action
    if ($action === 'download_zip') {
        $zip_file = __DIR__ . '/tables_export.zip';
        if (file_exists($zip_file)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="tables_export.zip"');
            header('Content-Length: ' . filesize($zip_file));
            readfile($zip_file);
            exit;
        } else {
            http_response_code(404);
            die("ZIP file not found. Please click Export first.");
        }
    }
    
    header('Content-Type: application/json');
    
    if ($action === 'export') {
        $res = runExport();
        if ($res['success']) {
            $zip_res = generateZip();
            $res['zip'] = $zip_res;
        }
        echo json_encode($res);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SQL Table Exporter</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #08090d;
            --card-bg: rgba(17, 19, 31, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --primary: #8b5cf6;
            --primary-glow: rgba(139, 92, 246, 0.15);
            --secondary: #d946ef;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --text: #f3f4f6;
            --text-muted: #8e95a5;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-color);
            background-image: 
                radial-gradient(circle at 15% 15%, rgba(139, 92, 246, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(217, 70, 239, 0.08) 0%, transparent 40%);
            background-attachment: fixed;
            color: var(--text);
            font-family: 'Outfit', sans-serif;
            min-height: 100vh;
            padding: 2rem 1rem;
            display: flex;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 900px;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            backdrop-filter: blur(12px);
            padding: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--secondary) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header h1 span.logo-icon {
            font-size: 2.2rem;
            background: none;
            -webkit-text-fill-color: initial;
        }

        .logout-btn {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .logout-btn:hover {
            background: var(--danger);
            color: #fff;
        }

        /* Login Layout */
        .login-wrapper {
            max-width: 400px;
            margin: 10% auto;
            width: 100%;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
        }

        .btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            border: none;
            color: #fff;
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            transition: opacity 0.2s ease, transform 0.1s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn:hover { opacity: 0.9; }
        .btn:active { transform: scale(0.98); }
        .btn:disabled {
            background: rgba(255,255,255,0.1) !important;
            color: var(--text-muted);
            cursor: not-allowed;
        }

        .btn-zip {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        /* Controls Panel */
        .actions-panel {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Info Display */
        .db-info {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
        }
        
        .db-info span {
            color: #fff;
            font-weight: 500;
        }

        /* Tables list */
        .tables-list-container {
            margin-top: 1rem;
        }

        .tables-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .tables-table th, .tables-table td {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .tables-table th {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .tables-table td.success { color: var(--success); }

        .spinner {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top: 2px solid #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .hidden { display: none !important; }
        
        .error-banner {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 0;
            color: var(--text-muted);
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <div class="header">
            <h1><span class="logo-icon">📦</span>SQL Table Exporter</h1>
            <?php if ($is_authorized && ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1')): ?>
                <a href="?action=logout" class="logout-btn">Log Out</a>
            <?php endif; ?>
        </div>

        <?php if (!$is_authorized): ?>
            <!-- Login -->
            <div class="glass-card login-wrapper">
                <div style="text-align: center; margin-bottom: 1.5rem;">
                    <h2>Unlock Exporter</h2>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Enter Security Key to proceed.</p>
                </div>
                <form id="loginForm">
                    <div class="form-group">
                        <label for="password" style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 0.25rem;">Security Key</label>
                        <input type="password" id="password" class="form-control" placeholder="••••••••" required autofocus>
                    </div>
                    <div id="loginError" class="error-banner hidden"></div>
                    <button type="submit" class="btn" style="width: 100%;">Unlock</button>
                </form>
            </div>
            <script>
                document.getElementById('loginForm').addEventListener('submit', function(e) {
                    e.preventDefault();
                    const password = document.getElementById('password').value;
                    const errorBox = document.getElementById('loginError');
                    errorBox.classList.add('hidden');
                    
                    const fd = new FormData();
                    fd.append('action', 'login');
                    fd.append('password', password);
                    
                    fetch(window.location.pathname, {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            window.location.reload();
                        } else {
                            errorBox.textContent = res.error || 'Authentication failed.';
                            errorBox.classList.remove('hidden');
                        }
                    })
                    .catch(err => {
                        errorBox.textContent = 'Server connection error.';
                        errorBox.classList.remove('hidden');
                    });
                });
            </script>
        <?php else: ?>
            <!-- Dashboard Main -->
            <div class="glass-card">
                <div class="db-info">
                    Database Host: <span>localhost</span> &nbsp;|&nbsp; Database: <span>if0_40845939_clarity_db</span> &nbsp;|&nbsp; Target Folder: <span>/tables/</span>
                </div>
                
                <div class="actions-panel">
                    <button id="exportBtn" class="btn">
                        <span>Scan & Export Database</span>
                    </button>
                    <a id="downloadZipBtn" href="?action=download_zip" class="btn btn-zip hidden">
                        <span>Download ZIP Archive</span>
                    </a>
                </div>
            </div>

            <div id="exportError" class="error-banner hidden"></div>

            <div class="glass-card">
                <h2 style="font-size: 1.2rem; font-weight: 600; color: #fff; margin-bottom: 1rem;">Export Files Status</h2>
                
                <div class="tables-list-container" id="tablesListContainer">
                    <div class="empty-state">Click "Scan & Export Database" above to write SQL files.</div>
                </div>
            </div>

            <script>
                const exportBtn = document.getElementById('exportBtn');
                const downloadZipBtn = document.getElementById('downloadZipBtn');
                const exportError = document.getElementById('exportError');
                const tablesListContainer = document.getElementById('tablesListContainer');

                exportBtn.addEventListener('click', () => {
                    exportBtn.disabled = true;
                    exportBtn.innerHTML = '<span class="spinner"></span> <span>Exporting...</span>';
                    exportError.classList.add('hidden');
                    downloadZipBtn.classList.add('hidden');

                    fetch(window.location.pathname + '?action=export')
                        .then(r => r.json())
                        .then(res => {
                            exportBtn.disabled = false;
                            exportBtn.innerHTML = '<span>Scan & Export Database</span>';
                            
                            if (res.success) {
                                renderExportedTables(res.tables);
                                if (res.zip && res.zip.success) {
                                    downloadZipBtn.classList.remove('hidden');
                                    const sizeMb = (res.zip.size / 1024 / 1024).toFixed(2);
                                    downloadZipBtn.querySelector('span').textContent = `Download ZIP Archive (${sizeMb} MB)`;
                                }
                            } else {
                                exportError.textContent = `Export failed: ${res.error}`;
                                exportError.classList.remove('hidden');
                            }
                        })
                        .catch(err => {
                            exportBtn.disabled = false;
                            exportBtn.innerHTML = '<span>Scan & Export Database</span>';
                            exportError.textContent = `Communication error: ${err.message}`;
                            exportError.classList.remove('hidden');
                        });
                });

                function renderExportedTables(tables) {
                    tablesListContainer.innerHTML = '';
                    
                    const keys = Object.keys(tables);
                    if (keys.length === 0) {
                        tablesListContainer.innerHTML = '<div class="empty-state">No tables found in this database.</div>';
                        return;
                    }
                    
                    const table = document.createElement('table');
                    table.className = 'tables-table';
                    table.innerHTML = `
                        <thead>
                            <tr>
                                <th>Table Name</th>
                                <th>Export File</th>
                                <th>Records Count</th>
                                <th>File Size</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;
                    
                    const tbody = table.querySelector('tbody');
                    keys.forEach(t => {
                        const info = tables[t];
                        const tr = document.createElement('tr');
                        const sizeKb = (info.size / 1024).toFixed(2);
                        
                        tr.innerHTML = `
                            <td style="font-weight: 500; color: #fff;">\`${t}\`</td>
                            <td style="font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">${info.file}</td>
                            <td>${info.rows} rows</td>
                            <td>${sizeKb} KB</td>
                            <td class="success">✓ Exported</td>
                        `;
                        tbody.appendChild(tr);
                    });
                    
                    tablesListContainer.appendChild(table);
                }
            </script>
        <?php endif; ?>
        
    </div>
</body>
</html>

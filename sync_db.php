<?php
// Enable strict error reporting for debugging
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

// Determine if we are in CLI mode
$is_cli = (php_sapi_name() === 'cli');

// Define connection helper functions
function getLocalConnection() {
    try {
        if (file_exists(__DIR__ . '/config/database.php')) {
            require_once __DIR__ . '/config/database.php';
            if (class_exists('Database')) {
                $db = Database::getInstance();
                $pdo = $db->getConnection();
                if ($pdo instanceof PDO) {
                    return $pdo;
                }
            }
        }
    } catch (Exception $e) {
        // Fall back to direct
    }
    
    return new PDO("mysql:host=localhost;dbname=if0_40845939_clarity_db;charset=utf8mb4", "root", "", [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
}

function getRemoteConnection($config) {
    $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['name']};charset=utf8mb4";
    return new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 10,
    ]);
}

// Database schema structures extractor
function getDatabaseSchema($pdo) {
    $schema = [];
    $tables_stmt = $pdo->query("SHOW TABLES");
    $tables = $tables_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        $schema[$table] = [
            'columns' => [],
            'indexes' => [],
            'create_table_sql' => ''
        ];
        
        $create_stmt = $pdo->query("SHOW CREATE TABLE `" . $table . "`");
        $create_row = $create_stmt->fetch();
        $schema[$table]['create_table_sql'] = $create_row['Create Table'] ?? '';
        
        $columns_stmt = $pdo->query("DESCRIBE `" . $table . "`");
        $columns = $columns_stmt->fetchAll();
        foreach ($columns as $col) {
            $schema[$table]['columns'][$col['Field']] = [
                'Type' => $col['Type'],
                'Null' => $col['Null'],
                'Key' => $col['Key'],
                'Default' => $col['Default'],
                'Extra' => $col['Extra'],
            ];
        }
        
        $indexes_stmt = $pdo->query("SHOW INDEX FROM `" . $table . "`");
        $indexes = $indexes_stmt->fetchAll();
        foreach ($indexes as $idx) {
            $key_name = $idx['Key_name'];
            if (!isset($schema[$table]['indexes'][$key_name])) {
                $schema[$table]['indexes'][$key_name] = [
                    'Non_unique' => $idx['Non_unique'],
                    'Columns' => [],
                ];
            }
            $schema[$table]['indexes'][$key_name]['Columns'][$idx['Seq_in_index']] = $idx['Column_name'];
        }
        
        foreach ($schema[$table]['indexes'] as $key_name => $idx_data) {
            ksort($schema[$table]['indexes'][$key_name]['Columns']);
            $schema[$table]['indexes'][$key_name]['Columns'] = array_values($schema[$table]['indexes'][$key_name]['Columns']);
        }
    }
    
    return $schema;
}

// Schema comparisons
function compareSchemas($src, $dst) {
    $diff = [
        'tables_to_create' => [],
        'tables_to_drop' => [],
        'columns_to_add' => [],
        'columns_to_modify' => [],
        'columns_to_drop' => [],
        'indexes_to_add' => [],
        'indexes_to_drop' => [],
    ];
    
    foreach ($src as $table => $table_data) {
        if (!isset($dst[$table])) {
            $diff['tables_to_create'][$table] = $table_data['create_table_sql'];
            continue;
        }
        
        $dst_table = $dst[$table];
        
        foreach ($table_data['columns'] as $col_name => $col_data) {
            if (!isset($dst_table['columns'][$col_name])) {
                $diff['columns_to_add'][$table][$col_name] = $col_data;
            } else {
                $dst_col = $dst_table['columns'][$col_name];
                $is_different = ($col_data['Type'] !== $dst_col['Type'] ||
                                 $col_data['Null'] !== $dst_col['Null'] ||
                                 $col_data['Default'] !== $dst_col['Default'] ||
                                 $col_data['Extra'] !== $dst_col['Extra']);
                if ($is_different) {
                    $diff['columns_to_modify'][$table][$col_name] = $col_data;
                }
            }
        }
        
        foreach ($dst_table['columns'] as $col_name => $col_data) {
            if (!isset($table_data['columns'][$col_name])) {
                $diff['columns_to_drop'][$table][] = $col_name;
            }
        }
        
        foreach ($table_data['indexes'] as $key_name => $idx_data) {
            if (!isset($dst_table['indexes'][$key_name])) {
                $diff['indexes_to_add'][$table][$key_name] = $idx_data;
            } else {
                $dst_idx = $dst_table['indexes'][$key_name];
                $is_different = ($idx_data['Non_unique'] != $dst_idx['Non_unique'] ||
                                 $idx_data['Columns'] !== $dst_idx['Columns']);
                if ($is_different) {
                    $diff['indexes_to_drop'][$table][] = $key_name;
                    $diff['indexes_to_add'][$table][$key_name] = $idx_data;
                }
            }
        }
        
        foreach ($dst_table['indexes'] as $key_name => $idx_data) {
            if (!isset($table_data['indexes'][$key_name])) {
                $diff['indexes_to_drop'][$table][] = $key_name;
            }
        }
    }
    
    foreach ($dst as $table => $table_data) {
        if (!isset($src[$table])) {
            $diff['tables_to_drop'][] = $table;
        }
    }
    
    return $diff;
}

function getColumnSqlDefinition($pdo, $col_name, $col_data) {
    $sql = "`$col_name` " . $col_data['Type'];
    if ($col_data['Null'] === 'NO') {
        $sql .= " NOT NULL";
    } else {
        $sql .= " NULL";
    }
    
    if ($col_data['Default'] !== null) {
        if (strtoupper($col_data['Default']) === 'CURRENT_TIMESTAMP') {
            $sql .= " DEFAULT CURRENT_TIMESTAMP";
        } else {
            $sql .= " DEFAULT " . $pdo->quote($col_data['Default']);
        }
    }
    
    if ($col_data['Extra'] !== '') {
        $sql .= " " . $col_data['Extra'];
    }
    
    return $sql;
}

function generateSchemaQueries($diff, $pdo) {
    $queries = [];
    
    foreach ($diff['tables_to_create'] as $table => $create_sql) {
        $queries[] = $create_sql;
    }
    
    foreach ($diff['columns_to_drop'] as $table => $cols) {
        foreach ($cols as $col) {
            $queries[] = "ALTER TABLE `$table` DROP COLUMN `$col`";
        }
    }
    
    foreach ($diff['columns_to_add'] as $table => $cols) {
        foreach ($cols as $col_name => $col_data) {
            $definition = getColumnSqlDefinition($pdo, $col_name, $col_data);
            $queries[] = "ALTER TABLE `$table` ADD COLUMN $definition";
        }
    }
    
    foreach ($diff['columns_to_modify'] as $table => $cols) {
        foreach ($cols as $col_name => $col_data) {
            $definition = getColumnSqlDefinition($pdo, $col_name, $col_data);
            $queries[] = "ALTER TABLE `$table` MODIFY COLUMN $definition";
        }
    }
    
    foreach ($diff['indexes_to_drop'] as $table => $indexes) {
        foreach ($indexes as $key_name) {
            if ($key_name === 'PRIMARY') {
                $queries[] = "ALTER TABLE `$table` DROP PRIMARY KEY";
            } else {
                $queries[] = "ALTER TABLE `$table` DROP INDEX `$key_name`";
            }
        }
    }
    
    foreach ($diff['indexes_to_add'] as $table => $indexes) {
        foreach ($indexes as $key_name => $idx_data) {
            $cols_str = implode(', ', array_map(function($c) { return "`$c`"; }, $idx_data['Columns']));
            if ($key_name === 'PRIMARY') {
                $queries[] = "ALTER TABLE `$table` ADD PRIMARY KEY ($cols_str)";
            } elseif ($idx_data['Non_unique'] == 0) {
                $queries[] = "ALTER TABLE `$table` ADD UNIQUE KEY `$key_name` ($cols_str)";
            } else {
                $queries[] = "ALTER TABLE `$table` ADD INDEX `$key_name` ($cols_str)";
            }
        }
    }
    
    foreach ($diff['tables_to_drop'] as $table) {
        $queries[] = "DROP TABLE `$table`";
    }
    
    return $queries;
}

function getPrimaryKey($columns, $indexes) {
    if (isset($indexes['PRIMARY'])) {
        return $indexes['PRIMARY']['Columns'];
    }
    foreach ($indexes as $key_name => $idx) {
        if ($idx['Non_unique'] == 0) {
            return $idx['Columns'];
        }
    }
    return null;
}

function compareTableData($src_pdo, $dst_pdo, $table, $primary_key) {
    if (!$primary_key) {
        $cnt = $src_pdo->query("SELECT COUNT(*) FROM `$table`")->fetchColumn();
        return [
            'mode' => 'truncate_insert',
            'src_count' => (int)$cnt,
            'inserts' => [],
            'updates' => [],
            'deletes' => []
        ];
    }
    
    $src_rows = $src_pdo->query("SELECT * FROM `$table`")->fetchAll();
    $dst_rows = $dst_pdo->query("SELECT * FROM `$table`")->fetchAll();
    
    $src_map = [];
    $dst_map = [];
    
    $get_key = function($row) use ($primary_key) {
        $parts = [];
        foreach ($primary_key as $col) {
            $parts[] = $row[$col] ?? '';
        }
        return implode('||', $parts);
    };
    
    foreach ($src_rows as $row) {
        $src_map[$get_key($row)] = $row;
    }
    
    foreach ($dst_rows as $row) {
        $dst_map[$get_key($row)] = $row;
    }
    
    $inserts = [];
    $updates = [];
    $deletes = [];
    
    foreach ($src_map as $key => $src_row) {
        if (!isset($dst_map[$key])) {
            $inserts[] = $src_row;
        } else {
            $dst_row = $dst_map[$key];
            $differs = false;
            foreach ($src_row as $col => $val) {
                if (array_key_exists($col, $dst_row)) {
                    if ($val !== $dst_row[$col]) {
                        $differs = true;
                        break;
                    }
                } else {
                    $differs = true;
                    break;
                }
            }
            if ($differs) {
                $updates[] = $src_row;
            }
        }
    }
    
    foreach ($dst_map as $key => $dst_row) {
        if (!isset($src_map[$key])) {
            $deletes[] = $dst_row;
        }
    }
    
    return [
        'mode' => 'row_diff',
        'inserts' => $inserts,
        'updates' => $updates,
        'deletes' => $deletes
    ];
}

function getRowInsertSql($pdo, $table, $row) {
    $cols = array_map(function($c) { return "`$c`"; }, array_keys($row));
    $vals = array_map(function($v) use ($pdo) {
        if ($v === null) return 'NULL';
        return $pdo->quote($v);
    }, array_values($row));
    
    return "INSERT INTO `$table` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ")";
}

function getRowUpdateSql($pdo, $table, $row, $primary_key) {
    $sets = [];
    foreach ($row as $col => $val) {
        if (in_array($col, $primary_key)) continue;
        if ($val === null) {
            $sets[] = "`$col` = NULL";
        } else {
            $sets[] = "`$col` = " . $pdo->quote($val);
        }
    }
    
    $where = [];
    foreach ($primary_key as $col) {
        if ($row[$col] === null) {
            $where[] = "`$col` IS NULL";
        } else {
            $where[] = "`$col` = " . $pdo->quote($row[$col]);
        }
    }
    
    return "UPDATE `$table` SET " . implode(', ', $sets) . " WHERE " . implode(' AND ', $where);
}

function getRowDeleteSql($pdo, $table, $row, $primary_key) {
    $where = [];
    foreach ($primary_key as $col) {
        if ($row[$col] === null) {
            $where[] = "`$col` IS NULL";
        } else {
            $where[] = "`$col` = " . $pdo->quote($row[$col]);
        }
    }
    return "DELETE FROM `$table` WHERE " . implode(' AND ', $where);
}

function truncate_log_query($query) {
    if (strlen($query) > 120) {
        return substr($query, 0, 115) . "...";
    }
    return $query;
}

function runComparison($remote_config) {
    try {
        $local_pdo = getLocalConnection();
        $remote_pdo = getRemoteConnection($remote_config);
        
        $local_schema = getDatabaseSchema($local_pdo);
        $remote_schema = getDatabaseSchema($remote_pdo);
        
        $direction = $_GET['direction'] ?? $_POST['direction'] ?? 'local-to-server';
        
        if ($direction === 'local-to-server') {
            $src_pdo = $local_pdo;
            $dst_pdo = $remote_pdo;
            $src_schema = $local_schema;
            $dst_schema = $remote_schema;
        } else {
            $src_pdo = $remote_pdo;
            $dst_pdo = $local_pdo;
            $src_schema = $remote_schema;
            $dst_schema = $local_schema;
        }
        
        $schema_diff = compareSchemas($src_schema, $dst_schema);
        $schema_queries = generateSchemaQueries($schema_diff, $dst_pdo);
        
        $data_diff = [];
        foreach ($src_schema as $table => $table_data) {
            if (!isset($dst_schema[$table])) {
                $cnt_stmt = $src_pdo->query("SELECT COUNT(*) FROM `$table`");
                $count = (int)$cnt_stmt->fetchColumn();
                $data_diff[$table] = [
                    'mode' => 'truncate_insert',
                    'src_count' => $count,
                    'inserts' => $count,
                    'updates' => 0,
                    'deletes' => 0,
                    'message' => 'Table will be created and ' . $count . ' records inserted.'
                ];
                continue;
            }
            
            $primary_key = getPrimaryKey($table_data['columns'], $table_data['indexes']);
            $table_diff = compareTableData($src_pdo, $dst_pdo, $table, $primary_key);
            
            if ($table_diff['mode'] === 'truncate_insert') {
                if ($table_diff['src_count'] > 0) {
                    $data_diff[$table] = [
                        'mode' => 'truncate_insert',
                        'src_count' => $table_diff['src_count'],
                        'inserts' => $table_diff['src_count'],
                        'updates' => 0,
                        'deletes' => 0,
                        'message' => 'No PK: will truncate target and reload ' . $table_diff['src_count'] . ' rows.'
                    ];
                }
            } else {
                $ins_count = count($table_diff['inserts']);
                $upd_count = count($table_diff['updates']);
                $del_count = count($table_diff['deletes']);
                
                if ($ins_count > 0 || $upd_count > 0 || $del_count > 0) {
                    $data_diff[$table] = [
                        'mode' => 'row_diff',
                        'inserts' => $ins_count,
                        'updates' => $upd_count,
                        'deletes' => $del_count,
                        'message' => "Inserts: $ins_count, Updates: $upd_count, Deletes: $del_count"
                    ];
                }
            }
        }
        
        return [
            'success' => true,
            'direction' => $direction,
            'schema_diff' => $schema_diff,
            'schema_queries' => $schema_queries,
            'data_diff' => $data_diff
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

function runSync($remote_config) {
    try {
        $direction = $_POST['direction'] ?? 'local-to-server';
        
        $local_pdo = getLocalConnection();
        $remote_pdo = getRemoteConnection($remote_config);
        
        if ($direction === 'local-to-server') {
            $src_pdo = $local_pdo;
            $dst_pdo = $remote_pdo;
        } else {
            $src_pdo = $remote_pdo;
            $dst_pdo = $local_pdo;
        }
        
        $src_schema = getDatabaseSchema($src_pdo);
        $dst_schema = getDatabaseSchema($dst_pdo);
        
        $schema_diff = compareSchemas($src_schema, $dst_schema);
        $schema_queries = generateSchemaQueries($schema_diff, $dst_pdo);
        
        $logs = [];
        $logs[] = "[INFO] Starting database synchronization (" . ($direction === 'local-to-server' ? "Local -> Server" : "Server -> Local") . ")";
        
        // Disable foreign keys
        $dst_pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $logs[] = "[INFO] Temporarily disabled foreign key checks.";
        
        // 1. Run Schema Changes
        if (!empty($schema_queries)) {
            $logs[] = "[INFO] Applying schema changes (" . count($schema_queries) . " queries)...";
            foreach ($schema_queries as $query) {
                try {
                    $dst_pdo->exec($query);
                    $logs[] = "[SUCCESS] Executed: " . truncate_log_query($query);
                } catch (Exception $ex) {
                    $logs[] = "[ERROR] Failed executing: $query. Reason: " . $ex->getMessage();
                    $dst_pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                    return ['success' => false, 'logs' => $logs, 'error' => "Schema sync failed: " . $ex->getMessage()];
                }
            }
        } else {
            $logs[] = "[INFO] No schema changes detected.";
        }
        
        // Recalculate schemas if schema changes were applied
        if (!empty($schema_queries)) {
            $dst_schema = getDatabaseSchema($dst_pdo);
        }
        
        // 2. Run Data Sync
        $logs[] = "[INFO] Syncing data...";
        foreach ($src_schema as $table => $table_data) {
            $primary_key = getPrimaryKey($table_data['columns'], $table_data['indexes']);
            $table_diff = compareTableData($src_pdo, $dst_pdo, $table, $primary_key);
            
            if ($table_diff['mode'] === 'truncate_insert') {
                if ($table_diff['src_count'] > 0) {
                    $logs[] = "[INFO] Table `$table` (No PK): truncating and inserting " . $table_diff['src_count'] . " records...";
                    try {
                        $dst_pdo->exec("TRUNCATE TABLE `$table`");
                        $rows = $src_pdo->query("SELECT * FROM `$table`")->fetchAll();
                        foreach ($rows as $row) {
                            $insert_sql = getRowInsertSql($dst_pdo, $table, $row);
                            $dst_pdo->exec($insert_sql);
                        }
                        $logs[] = "[SUCCESS] Table `$table` fully synced.";
                    } catch (Exception $ex) {
                        $logs[] = "[ERROR] Table `$table` sync failed: " . $ex->getMessage();
                        $dst_pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                        return ['success' => false, 'logs' => $logs, 'error' => "Data sync failed on `$table`: " . $ex->getMessage()];
                    }
                }
            } else {
                $ins = $table_diff['inserts'];
                $upd = $table_diff['updates'];
                $del = $table_diff['deletes'];
                
                if (!empty($ins) || !empty($upd) || !empty($del)) {
                    $logs[] = "[INFO] Table `$table`: applying data changes (Inserts: " . count($ins) . ", Updates: " . count($upd) . ", Deletes: " . count($del) . ")...";
                    
                    $dst_pdo->beginTransaction();
                    try {
                        foreach ($del as $row) {
                            $sql = getRowDeleteSql($dst_pdo, $table, $row, $primary_key);
                            $dst_pdo->exec($sql);
                        }
                        
                        foreach ($upd as $row) {
                            $sql = getRowUpdateSql($dst_pdo, $table, $row, $primary_key);
                            $dst_pdo->exec($sql);
                        }
                        
                        foreach ($ins as $row) {
                            $sql = getRowInsertSql($dst_pdo, $table, $row);
                            $dst_pdo->exec($sql);
                        }
                        
                        $dst_pdo->commit();
                        $logs[] = "[SUCCESS] Table `$table` data updated.";
                    } catch (Exception $ex) {
                        $dst_pdo->rollBack();
                        $logs[] = "[ERROR] Table `$table` data sync failed: " . $ex->getMessage();
                        $dst_pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                        return ['success' => false, 'logs' => $logs, 'error' => "Data sync failed on `$table`: " . $ex->getMessage()];
                    }
                }
            }
        }
        
        $dst_pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        $logs[] = "[INFO] Re-enabled foreign key checks.";
        $logs[] = "[SUCCESS] Database synchronization completed successfully.";
        
        return [
            'success' => true,
            'logs' => $logs
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Handle CLI mode
if ($is_cli) {
    $direction = 'local-to-server';
    $run = false;
    
    foreach ($argv as $arg) {
        if ($arg === '--direction=server-to-local') {
            $direction = 'server-to-local';
        }
        if ($arg === '--direction=local-to-server') {
            $direction = 'local-to-server';
        }
        if ($arg === '--run') {
            $run = true;
        }
    }
    
    echo "==========================================\n";
    echo " MySQL Database Sync Engine (CLI Mode)\n";
    echo " Direction: " . ($direction === 'local-to-server' ? "Local -> Server" : "Server -> Local") . "\n";
    echo "==========================================\n\n";
    
    echo "Step 1: Running comparison...\n";
    $comp = runComparison($remote_config);
    if (!$comp['success']) {
        echo "Error comparing databases: " . $comp['error'] . "\n";
        exit(1);
    }
    
    $diff = $comp['schema_diff'];
    $queries = $comp['schema_queries'];
    $data_diff = $comp['data_diff'];
    
    echo "\n[SCHEMA DIFFERENCES]\n";
    if (empty($queries)) {
        echo "No schema differences found.\n";
    } else {
        echo count($queries) . " schema changes to apply:\n";
        foreach ($queries as $q) {
            echo "  > $q\n";
        }
    }
    
    echo "\n[DATA DIFFERENCES]\n";
    if (empty($data_diff)) {
        echo "No data differences found.\n";
    } else {
        foreach ($data_diff as $table => $info) {
            echo "  Table `$table`: " . $info['message'] . "\n";
        }
    }
    
    if ($run) {
        echo "\nStep 2: Executing synchronization...\n";
        $_POST['direction'] = $direction;
        $sync_result = runSync($remote_config);
        
        echo "\n[EXECUTION LOGS]\n";
        foreach ($sync_result['logs'] ?? [] as $log) {
            echo "  $log\n";
        }
        
        if ($sync_result['success']) {
            echo "\nSynchronization completed successfully!\n";
            exit(0);
        } else {
            echo "\nSynchronization failed: " . ($sync_result['error'] ?? 'Unknown Error') . "\n";
            exit(1);
        }
    } else {
        echo "\nDry run completed. Run with `--run` to apply changes.\n";
        exit(0);
    }
}

// Handle AJAX actions for Web UI
if (isset($_POST['action']) || isset($_GET['action'])) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    // Security check: Only allow access to AJAX actions if authorized
    $is_authorized = isset($_SESSION['db_sync_auth']) && $_SESSION['db_sync_auth'] === true;
    if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
        $is_authorized = true;
    }
    
    if (!$is_authorized && $action !== 'login') {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }
    
    header('Content-Type: application/json');
    
    if ($action === 'login') {
        $password = $_POST['password'] ?? '';
        if ($password === $remote_config['pass']) {
            $_SESSION['db_sync_auth'] = true;
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'error' => 'Invalid Security Key']);
            exit;
        }
    }
    
    if ($action === 'check_connections') {
        $local_ok = false;
        $remote_ok = false;
        $local_err = '';
        $remote_err = '';
        
        try {
            getLocalConnection();
            $local_ok = true;
        } catch (Exception $e) {
            $local_err = $e->getMessage();
        }
        
        try {
            getRemoteConnection($remote_config);
            $remote_ok = true;
        } catch (Exception $e) {
            $remote_err = $e->getMessage();
        }
        
        echo json_encode([
            'success' => true,
            'local' => ['ok' => $local_ok, 'dbname' => 'if0_40845939_clarity_db', 'host' => 'localhost', 'error' => $local_err],
            'remote' => ['ok' => $remote_ok, 'dbname' => $remote_config['name'], 'host' => $remote_config['host'], 'error' => $remote_err]
        ]);
        exit;
    }
    
    if ($action === 'compare') {
        echo json_encode(runComparison($remote_config));
        exit;
    }
    
    if ($action === 'sync') {
        echo json_encode(runSync($remote_config));
        exit;
    }
}

// Display HTML UI
$is_authorized = isset($_SESSION['db_sync_auth']) && $_SESSION['db_sync_auth'] === true;
if ($_SERVER['REMOTE_ADDR'] === '127.0.0.1' || $_SERVER['REMOTE_ADDR'] === '::1') {
    $is_authorized = true;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['db_sync_auth']);
    header('Location: sync_db.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Sync Engine</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #08090d;
            --card-bg: rgba(17, 19, 31, 0.7);
            --border-color: rgba(255, 255, 255, 0.08);
            --primary: #6366f1;
            --primary-glow: rgba(99, 102, 241, 0.15);
            --secondary: #a855f7;
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
                radial-gradient(circle at 15% 15%, rgba(99, 102, 241, 0.12) 0%, transparent 40%),
                radial-gradient(circle at 85% 85%, rgba(168, 85, 247, 0.08) 0%, transparent 40%);
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
            max-width: 1100px;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        /* Glassmorphic card styling */
        .glass-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            backdrop-filter: blur(12px);
            padding: 1.5rem;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        /* App Title */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #a855f7 0%, #6366f1 100%);
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
            font-family: 'Outfit', sans-serif;
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

        .login-header {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .login-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
        }

        .form-group label {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .form-control {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            color: #fff;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-family: 'Outfit', sans-serif;
            font-size: 1rem;
            transition: border-color 0.2s ease;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn:disabled {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-muted);
            cursor: not-allowed;
        }

        /* Dashboard specific CSS */
        .db-status-grid {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 1.5rem;
            align-items: center;
        }

        @media (max-width: 768px) {
            .db-status-grid {
                grid-template-columns: 1fr;
            }
            .sync-direction-switch {
                transform: rotate(90deg);
                margin: 1rem 0;
            }
        }

        .db-card {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .db-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .db-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #fff;
        }

        /* Pulsing Online Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.25rem 0.6rem;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.05);
        }

        .status-badge.online {
            color: var(--success);
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }

        .status-badge.offline {
            color: var(--danger);
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .status-badge.checking {
            color: var(--warning);
            background: rgba(245, 158, 11, 0.1);
            border: 1px solid rgba(245, 158, 11, 0.2);
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: currentColor;
            display: inline-block;
        }

        .status-badge.online .pulse-dot {
            animation: pulse-green 1.5s infinite;
        }

        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        .db-detail {
            font-size: 0.9rem;
            color: var(--text-muted);
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 0.5rem 1rem;
        }

        .db-detail span.label {
            font-weight: 500;
            color: rgba(255, 255, 255, 0.4);
        }

        /* Direction Selector Switch */
        .sync-direction-switch {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }

        .switch-toggle-pill {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 30px;
            padding: 0.3rem;
            display: flex;
            position: relative;
            cursor: pointer;
            width: 220px;
            user-select: none;
        }

        .switch-option {
            flex: 1;
            text-align: center;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.5rem 0;
            color: var(--text-muted);
            z-index: 2;
            transition: color 0.3s ease;
        }

        .switch-option.active {
            color: #fff;
        }

        .switch-slider {
            position: absolute;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            top: 0.3rem;
            bottom: 0.3rem;
            width: calc(50% - 0.3rem);
            border-radius: 25px;
            z-index: 1;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .switch-toggle-pill[data-val="server-to-local"] .switch-slider {
            transform: translateX(100%);
        }

        .direction-arrow {
            font-size: 1.5rem;
            color: var(--primary);
            animation: bounce-horizontal 2s infinite;
        }

        @keyframes bounce-horizontal {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(5px); }
        }

        /* Controls card */
        .actions-panel {
            display: flex;
            gap: 1rem;
            justify-content: flex-start;
        }

        .btn-success {
            background: var(--success);
        }
        .btn-success:hover {
            background: #0ea5e9;
        }

        /* Diff Viewer Section */
        .diff-section-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .diff-badge {
            background: rgba(255, 255, 255, 0.08);
            border-radius: 12px;
            font-size: 0.8rem;
            padding: 0.2rem 0.6rem;
            color: #fff;
        }

        .diff-badge.add { background: rgba(16, 185, 129, 0.15); color: var(--success); border: 1px solid rgba(16, 185, 129, 0.2); }
        .diff-badge.modify { background: rgba(245, 158, 11, 0.15); color: var(--warning); border: 1px solid rgba(245, 158, 11, 0.2); }
        .diff-badge.delete { background: rgba(239, 68, 68, 0.15); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }

        .diff-content-box {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .accordion {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
            background: rgba(0, 0, 0, 0.15);
        }

        .accordion-header {
            padding: 0.75rem 1rem;
            background: rgba(255, 255, 255, 0.02);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
            font-size: 0.95rem;
            user-select: none;
        }

        .accordion-header:hover {
            background: rgba(255, 255, 255, 0.04);
        }

        .accordion-body {
            padding: 1rem;
            border-top: 1px solid var(--border-color);
            display: none;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            background: rgba(0, 0, 0, 0.3);
            white-space: pre-wrap;
            color: #cbd5e1;
        }

        .diff-row {
            padding: 0.25rem 0;
            display: flex;
            gap: 0.5rem;
        }

        .diff-row.add { color: var(--success); }
        .diff-row.modify { color: var(--warning); }
        .diff-row.delete { color: var(--danger); }

        /* Data changes table styling */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .data-table th, .data-table td {
            text-align: left;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .data-table th {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* Console styling */
        .console-container {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .console-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .console-window {
            background: #040508;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            padding: 1rem;
            height: 250px;
            overflow-y: auto;
            color: #d1d5db;
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            box-shadow: inset 0 4px 12px rgba(0,0,0,0.8);
        }

        .console-line {
            line-height: 1.4;
        }

        .console-line.info { color: #60a5fa; }
        .console-line.success { color: var(--success); }
        .console-line.warning { color: var(--warning); }
        .console-line.error { color: var(--danger); }

        .hidden {
            display: none !important;
        }

        .error-banner {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: var(--danger);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 2rem 0;
            color: var(--text-muted);
            font-style: italic;
        }

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
    </style>
</head>
<body>
    <div class="container">
        
        <!-- Header -->
        <div class="header">
            <h1><span class="logo-icon">⚡</span>Database Sync Engine</h1>
            <?php if ($is_authorized && ($_SERVER['REMOTE_ADDR'] !== '127.0.0.1' && $_SERVER['REMOTE_ADDR'] !== '::1')): ?>
                <a href="sync_db.php?action=logout" class="logout-btn">Log Out</a>
            <?php endif; ?>
        </div>

        <?php if (!$is_authorized): ?>
            <!-- Login Card -->
            <div class="glass-card login-wrapper">
                <div class="login-header">
                    <h2>Authentication Required</h2>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Enter the Remote Database Password to access this tool.</p>
                </div>
                <form id="loginForm">
                    <div class="form-group">
                        <label for="password">Security Key (Remote DB Password)</label>
                        <input type="password" id="password" class="form-control" placeholder="••••••••" required autofocus>
                    </div>
                    <div id="loginError" class="error-banner hidden" style="margin-bottom: 1rem;"></div>
                    <button type="submit" class="btn" style="width: 100%;">
                        <span>Unlock Dashboard</span>
                    </button>
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
                    
                    fetch('sync_db.php', {
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
            
            <!-- Connection Status -->
            <div class="glass-card db-status-grid">
                
                <!-- Local DB -->
                <div class="db-card">
                    <div class="db-card-header">
                        <span class="db-title">Local Database</span>
                        <span id="localStatus" class="status-badge checking"><span class="pulse-dot"></span>Checking</span>
                    </div>
                    <div class="db-detail">
                        <span class="label">Host:</span>
                        <span>localhost</span>
                        <span class="label">Database:</span>
                        <span>if0_40845939_clarity_db</span>
                        <span class="label">User:</span>
                        <span>root</span>
                    </div>
                </div>

                <!-- Sync Direction Selector -->
                <div class="sync-direction-switch">
                    <div class="switch-toggle-pill" id="directionToggle" data-val="local-to-server">
                        <div class="switch-slider"></div>
                        <div class="switch-option active" id="optLocalServer" data-val="local-to-server">Local ➔ Server</div>
                        <div class="switch-option" id="optServerLocal" data-val="server-to-local">Server ➔ Local</div>
                    </div>
                    <div class="direction-arrow" id="dirArrow">➔</div>
                </div>

                <!-- Remote DB -->
                <div class="db-card">
                    <div class="db-card-header">
                        <span class="db-title">Remote Server DB</span>
                        <span id="remoteStatus" class="status-badge checking"><span class="pulse-dot"></span>Checking</span>
                    </div>
                    <div class="db-detail">
                        <span class="label">Host:</span>
                        <span>sql109.infinityfree.com</span>
                        <span class="label">Database:</span>
                        <span>if0_40845939_clarity_db</span>
                        <span class="label">User:</span>
                        <span>if0_40845939</span>
                    </div>
                </div>

            </div>

            <!-- Controls Panel -->
            <div class="glass-card actions-panel">
                <button id="compareBtn" class="btn">
                    <span>Compare Databases</span>
                </button>
                <button id="syncBtn" class="btn btn-success" disabled>
                    <span>Execute Sync</span>
                </button>
            </div>

            <!-- General Error Notification Banner -->
            <div id="generalError" class="error-banner hidden"></div>

            <!-- Differences Result Area -->
            <div id="diffResults" class="hidden" style="display: flex; flex-direction: column; gap: 1.5rem;">
                
                <!-- Schema Diffs -->
                <div class="glass-card">
                    <h2 class="diff-section-title">
                        <span>Schema Differences</span>
                        <span id="schemaCountBadge" class="diff-badge">0</span>
                    </h2>
                    <div id="schemaDiffContainer" class="diff-content-box">
                        <div class="empty-state">Run comparison to analyze.</div>
                    </div>
                </div>

                <!-- Data Diffs -->
                <div class="glass-card">
                    <h2 class="diff-section-title">
                        <span>Data Differences</span>
                        <span id="dataCountBadge" class="diff-badge">0</span>
                    </h2>
                    <div id="dataDiffContainer" class="diff-content-box">
                        <div class="empty-state">Run comparison to analyze.</div>
                    </div>
                </div>

            </div>

            <!-- Execution Logger Console -->
            <div class="glass-card console-container">
                <div class="console-header">
                    <span style="font-weight: 600; color: #fff; font-size: 0.95rem;">Execution Logs</span>
                    <button id="clearConsoleBtn" style="background:none; border:none; color: var(--text-muted); cursor:pointer; font-size: 0.8rem; font-family:inherit;">Clear</button>
                </div>
                <div class="console-window" id="consoleLog">
                    <div class="console-line info">[SYSTEM] Console ready. Please click "Compare Databases" to see schema and data alignment.</div>
                </div>
            </div>

            <script>
                // State management
                let dbDirection = 'local-to-server';
                let hasCompared = false;
                let activeDiff = null;

                // Elements
                const directionToggle = document.getElementById('directionToggle');
                const optLocalServer = document.getElementById('optLocalServer');
                const optServerLocal = document.getElementById('optServerLocal');
                const dirArrow = document.getElementById('dirArrow');
                const compareBtn = document.getElementById('compareBtn');
                const syncBtn = document.getElementById('syncBtn');
                
                const localStatus = document.getElementById('localStatus');
                const remoteStatus = document.getElementById('remoteStatus');
                const generalError = document.getElementById('generalError');
                
                const diffResults = document.getElementById('diffResults');
                const schemaCountBadge = document.getElementById('schemaCountBadge');
                const dataCountBadge = document.getElementById('dataCountBadge');
                
                const schemaDiffContainer = document.getElementById('schemaDiffContainer');
                const dataDiffContainer = document.getElementById('dataDiffContainer');
                
                const consoleLog = document.getElementById('consoleLog');
                const clearConsoleBtn = document.getElementById('clearConsoleBtn');

                // Logger helper
                function log(type, text) {
                    const line = document.createElement('div');
                    line.className = `console-line ${type}`;
                    line.textContent = text;
                    consoleLog.appendChild(line);
                    consoleLog.scrollTop = consoleLog.scrollHeight;
                }

                // Check connection statuses initially
                function checkConnections() {
                    localStatus.className = 'status-badge checking';
                    localStatus.innerHTML = '<span class="pulse-dot"></span>Checking';
                    remoteStatus.className = 'status-badge checking';
                    remoteStatus.innerHTML = '<span class="pulse-dot"></span>Checking';

                    fetch('sync_db.php?action=check_connections')
                        .then(r => r.json())
                        .then(res => {
                            if (res.success) {
                                if (res.local.ok) {
                                    localStatus.className = 'status-badge online';
                                    localStatus.innerHTML = '<span class="pulse-dot"></span>Online';
                                } else {
                                    localStatus.className = 'status-badge offline';
                                    localStatus.innerHTML = '<span class="pulse-dot"></span>Offline';
                                    log('error', `[LOCAL DB ERROR]: ${res.local.error}`);
                                }
                                if (res.remote.ok) {
                                    remoteStatus.className = 'status-badge online';
                                    remoteStatus.innerHTML = '<span class="pulse-dot"></span>Online';
                                } else {
                                    remoteStatus.className = 'status-badge offline';
                                    remoteStatus.innerHTML = '<span class="pulse-dot"></span>Offline';
                                    log('error', `[REMOTE DB ERROR]: ${res.remote.error}`);
                                }
                            } else {
                                log('error', `Failed checking connections: ${res.error}`);
                            }
                        })
                        .catch(err => {
                            log('error', `Server error checking connections: ${err.message}`);
                        });
                }
                
                checkConnections();

                // Direction Selector Switch click listener
                directionToggle.addEventListener('click', (e) => {
                    const target = e.target.closest('.switch-option') || e.target.closest('.switch-slider');
                    if (!target) return;
                    
                    let newDir = 'local-to-server';
                    if (target.id === 'optServerLocal') {
                        newDir = 'server-to-local';
                    }
                    
                    if (dbDirection !== newDir) {
                        dbDirection = newDir;
                        directionToggle.setAttribute('data-val', dbDirection);
                        
                        document.querySelectorAll('.switch-option').forEach(el => el.classList.remove('active'));
                        if (dbDirection === 'local-to-server') {
                            optLocalServer.classList.add('active');
                            dirArrow.textContent = '➔';
                        } else {
                            optServerLocal.classList.add('active');
                            dirArrow.textContent = '⮈';
                        }
                        
                        log('info', `Changed direction: ${dbDirection === 'local-to-server' ? 'Local ➔ Server' : 'Server ➔ Local'}`);
                        // Reset comparisons
                        resetComparisonState();
                    }
                });

                function resetComparisonState() {
                    hasCompared = false;
                    syncBtn.disabled = true;
                    diffResults.classList.add('hidden');
                    schemaDiffContainer.innerHTML = '<div class="empty-state">Run comparison to analyze.</div>';
                    dataDiffContainer.innerHTML = '<div class="empty-state">Run comparison to analyze.</div>';
                    schemaCountBadge.textContent = '0';
                    dataCountBadge.textContent = '0';
                    schemaCountBadge.className = 'diff-badge';
                    dataCountBadge.className = 'diff-badge';
                }

                // Comparison triggers
                compareBtn.addEventListener('click', () => {
                    compareBtn.disabled = true;
                    compareBtn.innerHTML = '<span class="spinner"></span> <span>Comparing...</span>';
                    generalError.classList.add('hidden');
                    log('info', `Running schema and data comparison (${dbDirection})...`);

                    fetch(`sync_db.php?action=compare&direction=${dbDirection}`)
                        .then(r => r.json())
                        .then(res => {
                            compareBtn.disabled = false;
                            compareBtn.innerHTML = '<span>Compare Databases</span>';
                            
                            if (res.success) {
                                activeDiff = res;
                                renderDiffs(res);
                            } else {
                                generalError.textContent = `Comparison failed: ${res.error}`;
                                generalError.classList.remove('hidden');
                                log('error', `Comparison failed: ${res.error}`);
                            }
                        })
                        .catch(err => {
                            compareBtn.disabled = false;
                            compareBtn.innerHTML = '<span>Compare Databases</span>';
                            generalError.textContent = `Communication error: ${err.message}`;
                            generalError.classList.remove('hidden');
                            log('error', `Communication error: ${err.message}`);
                        });
                });

                // Clear console
                clearConsoleBtn.addEventListener('click', () => {
                    consoleLog.innerHTML = '';
                });

                // Rendering differences
                function renderDiffs(diffData) {
                    diffResults.classList.remove('hidden');
                    schemaDiffContainer.innerHTML = '';
                    dataDiffContainer.innerHTML = '';
                    
                    let schemaDiffCount = 0;
                    let dataDiffCount = 0;

                    // 1. Render Schema Diffs
                    const queries = diffData.schema_queries || [];
                    schemaDiffCount = queries.length;
                    schemaCountBadge.textContent = schemaDiffCount;
                    
                    if (schemaDiffCount === 0) {
                        schemaDiffContainer.innerHTML = '<div class="empty-state" style="color: var(--success)">Schema is perfectly in sync.</div>';
                        schemaCountBadge.className = 'diff-badge';
                    } else {
                        schemaCountBadge.className = 'diff-badge modify';
                        
                        // Let's list queries in an accordion for users to inspect
                        const acc = document.createElement('div');
                        acc.className = 'accordion';
                        
                        const accHeader = document.createElement('div');
                        accHeader.className = 'accordion-header';
                        accHeader.innerHTML = `<span>View DDL changes to be applied (${schemaDiffCount} queries)</span> <span>▼</span>`;
                        
                        const accBody = document.createElement('div');
                        accBody.className = 'accordion-body';
                        
                        queries.forEach((q, idx) => {
                            const line = document.createElement('div');
                            line.style.padding = '0.3rem 0';
                            line.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
                            line.textContent = `${idx + 1}. ${q};`;
                            accBody.appendChild(line);
                        });
                        
                        acc.appendChild(accHeader);
                        acc.appendChild(accBody);
                        schemaDiffContainer.appendChild(acc);

                        // Toggle accordion
                        accHeader.addEventListener('click', () => {
                            const show = accBody.style.display === 'block';
                            accBody.style.display = show ? 'none' : 'block';
                            accHeader.querySelector('span:last-child').textContent = show ? '▼' : '▲';
                        });
                    }

                    // 2. Render Data Diffs
                    const dataDiffs = diffData.data_diff || {};
                    const tablesWithDataDiff = Object.keys(dataDiffs).filter(k => {
                        const tableInfo = dataDiffs[k];
                        return tableInfo.inserts > 0 || tableInfo.updates > 0 || tableInfo.deletes > 0 || tableInfo.mode === 'truncate_insert';
                    });
                    
                    dataDiffCount = tablesWithDataDiff.length;
                    dataCountBadge.textContent = dataDiffCount;

                    if (dataDiffCount === 0) {
                        dataDiffContainer.innerHTML = '<div class="empty-state" style="color: var(--success)">All table data is in sync.</div>';
                        dataCountBadge.className = 'diff-badge';
                    } else {
                        dataCountBadge.className = 'diff-badge modify';
                        
                        const table = document.createElement('table');
                        table.className = 'data-table';
                        
                        table.innerHTML = `
                            <thead>
                                <tr>
                                    <th>Table Name</th>
                                    <th>Sync Mode</th>
                                    <th>Inserts</th>
                                    <th>Updates</th>
                                    <th>Deletes</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        `;
                        
                        const tbody = table.querySelector('tbody');
                        tablesWithDataDiff.forEach(t => {
                            const info = dataDiffs[t];
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td style="font-weight: 500; color: #fff;">\`${t}\`</td>
                                <td><span style="font-size: 0.8rem; background: rgba(255,255,255,0.05); padding: 0.15rem 0.4rem; border-radius: 4px;">${info.mode}</span></td>
                                <td class="${info.inserts > 0 ? 'diff-row add' : ''}" style="font-weight: 600;">${info.inserts}</td>
                                <td class="${info.updates > 0 ? 'diff-row modify' : ''}" style="font-weight: 600;">${info.updates}</td>
                                <td class="${info.deletes > 0 ? 'diff-row delete' : ''}" style="font-weight: 600;">${info.deletes}</td>
                                <td style="font-size: 0.85rem; color: var(--text-muted);">${info.message}</td>
                            `;
                            tbody.appendChild(tr);
                        });
                        
                        dataDiffContainer.appendChild(table);
                    }

                    // Enable sync button if there are differences
                    if (schemaDiffCount > 0 || dataDiffCount > 0) {
                        syncBtn.disabled = false;
                        log('warning', `Differences detected. Schema changes: ${schemaDiffCount}, Data table syncs needed: ${dataDiffCount}. Ready to synchronize.`);
                    } else {
                        syncBtn.disabled = true;
                        log('success', `Databases are completely aligned. No synchronization needed.`);
                    }
                }

                // Synchronization execution
                syncBtn.addEventListener('click', () => {
                    const targetDb = dbDirection === 'local-to-server' ? 'Server Database' : 'Local Database';
                    const confirmMsg = `WARNING: This action will apply all schema and data changes directly to the ${targetDb}!\n\nThis may modify tables, alter columns, and insert/update/delete records.\n\nAre you sure you want to proceed?`;
                    
                    if (!confirm(confirmMsg)) {
                        return;
                    }

                    syncBtn.disabled = true;
                    compareBtn.disabled = true;
                    syncBtn.innerHTML = '<span class="spinner"></span> <span>Syncing...</span>';
                    
                    log('info', `Initializing synchronization execution...`);
                    
                    const fd = new FormData();
                    fd.append('action', 'sync');
                    fd.append('direction', dbDirection);
                    
                    fetch('sync_db.php', {
                        method: 'POST',
                        body: fd
                    })
                    .then(r => r.json())
                    .then(res => {
                        syncBtn.innerHTML = '<span>Execute Sync</span>';
                        compareBtn.disabled = false;
                        
                        if (res.success) {
                            (res.logs || []).forEach(l => {
                                if (l.includes('[SUCCESS]')) log('success', l);
                                else if (l.includes('[ERROR]')) log('error', l);
                                else if (l.includes('[WARNING]')) log('warning', l);
                                else log('info', l);
                            });
                            
                            log('success', `Synchronization executed successfully! Check logs above for details.`);
                            resetComparisonState();
                            checkConnections();
                        } else {
                            (res.logs || []).forEach(l => {
                                if (l.includes('[ERROR]')) log('error', l);
                                else log('info', l);
                            });
                            generalError.textContent = `Sync failed: ${res.error}`;
                            generalError.classList.remove('hidden');
                            log('error', `Sync failed: ${res.error}`);
                        }
                    })
                    .catch(err => {
                        syncBtn.innerHTML = '<span>Execute Sync</span>';
                        compareBtn.disabled = false;
                        generalError.textContent = `Sync connection error: ${err.message}`;
                        generalError.classList.remove('hidden');
                        log('error', `Sync connection error: ${err.message}`);
                    });
                });
            </script>
        <?php endif; ?>

    </div>
</body>
</html>

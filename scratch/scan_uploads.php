<?php
require_once 'C:/xampp/htdocs/lynq/config/autoload.php';

$db = DatabaseConfig::getInstance();

echo "=== ALL FEASIBILITY CHECKS WITH IMAGES ===\n";
$rows = $db->getResults("SELECT id, assignment_id, backroom_network_snap, ups_available_snap, earthing_snap, router_antenna_snap, antenna_routing_snap, remarks_snap, created_at, updated_at FROM feasibility_checks ORDER BY id DESC LIMIT 20");
foreach ($rows as $r) {
    echo "ID: {$r['id']}, Assignment: {$r['assignment_id']}, Created: {$r['created_at']}, Updated: {$r['updated_at']}\n";
    echo "  - backroom_network: {$r['backroom_network_snap']}\n";
    echo "  - ups_available: {$r['ups_available_snap']}\n";
    echo "  - earthing: {$r['earthing_snap']}\n";
    echo "  - router_antenna: {$r['router_antenna_snap']}\n";
    echo "  - antenna_routing: {$r['antenna_routing_snap']}\n";
    echo "  - remarks_snap: {$r['remarks_snap']}\n";
    echo "--------------------------------------------------------\n";
}

echo "\n=== ALL FILES IN UPLOADS DIRECTORY ===\n";
$baseUploads = 'C:/xampp/htdocs/lynq/uploads';
function scanAll($dir) {
    $results = [];
    if (!is_dir($dir)) return $results;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . '/' . $item;
        if (is_dir($full)) {
            $results = array_merge($results, scanAll($full));
        } else {
            $results[] = str_replace('C:/xampp/htdocs/lynq/', '', $full) . " [" . filesize($full) . " bytes, " . date('Y-m-d H:i:s', filemtime($full)) . "]";
        }
    }
    return $results;
}

$allFiles = scanAll($baseUploads);
print_r($allFiles);

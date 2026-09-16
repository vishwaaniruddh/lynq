<?php
require_once 'C:/xampp/htdocs/lynq/config/autoload.php';

$srcDir = 'C:/xampp/htdocs/lynq/uploads/feasibility/1/';
$destDir = 'C:/xampp/htdocs/lynq/uploads/feasibility/53639/';

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

$snaps = [
    'backroom_network_snap' => 'backroom_network_snap_20251231215658_a10fe965.jpg',
    'ups_available_snap' => 'ups_available_snap_20251231215701_cef45bd7.jpg',
    'earthing_snap' => 'earthing_snap_20251231215702_883b5c28.jpg',
    'router_antenna_snap' => 'router_antenna_snap_20251231215659_4e87cbed.jpg',
    'antenna_routing_snap' => 'antenna_routing_snap_20251231215700_b5e96c56.jpg',
    'remarks_snap' => 'remarks_snap_20251231215703_48e197b0.jpg'
];

$updateCols = [];
$params = [];

foreach ($snaps as $col => $file) {
    $srcFile = $srcDir . $file;
    $destFile = $destDir . $file;
    if (file_exists($srcFile)) {
        copy($srcFile, $destFile);
        $relPath = 'uploads/feasibility/53639/' . $file;
        $updateCols[] = "`$col` = ?";
        $params[] = $relPath;
    }
}

if (!empty($updateCols)) {
    $sql = "UPDATE feasibility_checks SET " . implode(', ', $updateCols) . ", updated_at = NOW() WHERE id = 53639";
    $types = str_repeat('s', count($params));
    $db = DatabaseConfig::getInstance();
    $db->executeQuery($sql, $params, $types);
    echo "SUCCESS: Updated feasibility_checks ID 53639 with real photos from uploads/feasibility/1/\n";
}

$row = $db->getResults("SELECT id, backroom_network_snap, ups_available_snap, earthing_snap, router_antenna_snap, antenna_routing_snap, remarks_snap FROM feasibility_checks WHERE id = 53639");
print_r($row);

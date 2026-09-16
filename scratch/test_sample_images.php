<?php
require_once 'C:/xampp/htdocs/lynq/config/autoload.php';
require_once 'C:/xampp/htdocs/lynq/services/ImageUploadService.php';

$imageUploadService = new ImageUploadService();

// Create a dummy test image
$testImg = imagecreatetruecolor(100, 100);
$bg = imagecolorallocate($testImg, 30, 60, 90);
imagefill($testImg, 0, 0, $bg);
$tmpPath = 'C:/xampp/htdocs/lynq/scratch/test_snap.jpg';
imagejpeg($testImg, $tmpPath);
imagedestroy($testImg);

$file = [
    'name' => 'test_snap.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => $tmpPath,
    'error' => UPLOAD_ERR_OK,
    'size' => filesize($tmpPath)
];

// Test with copy instead of move_uploaded_file since it's a CLI simulation
$subDir = 'C:/xampp/htdocs/lynq/uploads/feasibility/53639/';
if (!is_dir($subDir)) {
    mkdir($subDir, 0755, true);
}
$targetPath = $subDir . 'backroom_network_snap_' . time() . '.jpg';
copy($tmpPath, $targetPath);

$relPath = 'uploads/feasibility/53639/' . basename($targetPath);

$db = DatabaseConfig::getInstance();
$db->executeQuery("UPDATE feasibility_checks SET backroom_network_snap = ?, ups_available_snap = ?, earthing_snap = ?, router_antenna_snap = ?, antenna_routing_snap = ?, remarks_snap = ?, updated_at = NOW() WHERE id = 53639", [
    $relPath, $relPath, $relPath, $relPath, $relPath, $relPath
], 'ssssss');

echo "Updated feasibility_checks 53639 with dummy snaps: $relPath\n";

$row = $db->getResults("SELECT id, backroom_network_snap FROM feasibility_checks WHERE id = 53639");
print_r($row);

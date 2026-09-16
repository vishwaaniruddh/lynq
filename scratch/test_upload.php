<?php
require_once 'C:/xampp/htdocs/lynq/config/autoload.php';
require_once 'C:/xampp/htdocs/lynq/services/ImageUploadService.php';

$db = DatabaseConfig::getInstance();
$rows = $db->getResults("SELECT id, assignment_id, backroom_network_snap, ups_available_snap, earthing_snap, router_antenna_snap, antenna_routing_snap, remarks_snap FROM feasibility_checks ORDER BY id DESC LIMIT 5");

echo "=== RECENT FEASIBILITY CHECKS IMAGES ===\n";
print_r($rows);

$uploadPath = 'C:/xampp/htdocs/lynq/uploads/feasibility/';
echo "\n=== UPLOAD DIRECTORY CONTENTS ===\n";
if (is_dir($uploadPath)) {
    $dirs = scandir($uploadPath);
    print_r($dirs);
} else {
    echo "Directory does not exist\n";
}

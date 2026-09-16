<?php
require_once 'c:/xampp/htdocs/lynq/config/autoload.php';

$db = DatabaseConfig::getInstance()->getConnection();
$res = $db->query("SELECT id, assigned_engineer_id, status FROM installations LIMIT 3");
echo "Sample Installations:\n";
while ($row = $res->fetch_assoc()) {
    print_r($row);
}

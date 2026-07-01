<?php
require_once 'config/autoload.php';

try {
    $db = DatabaseConfig::getInstance();
    $result = $db->getResults('DESCRIBE email_configurations');
    
    echo "Email configurations table structure:\n";
    foreach($result as $row) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . ($row['Default'] ?? 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
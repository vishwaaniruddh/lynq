<?php
/**
 * Cleanup login attempts for testing
 */

require_once __DIR__ . '/config/autoload.php';

$db = DatabaseConfig::getInstance();

// Clean up login attempts for localhost
$sql = "DELETE FROM login_attempts WHERE ip_address = '127.0.0.1'";
$stmt = $db->executeQuery($sql);
echo "Deleted: " . $stmt->affected_rows . " login attempts\n";
$stmt->close();

echo "Login attempts cleaned up.\n";

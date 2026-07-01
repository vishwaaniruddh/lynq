<?php
/**
 * Fix Admin User Status
 * Updates the admin user status to integer 1 (active)
 */

require_once __DIR__ . '/config/autoload.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Check current admin status
    $stmt = $db->query("SELECT id, username, status FROM users WHERE username = 'admin'");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "Current admin user:\n";
        print_r($user);
        
        // Update status to 1
        $stmt = $db->prepare("UPDATE users SET status = 1 WHERE username = 'admin'");
        $stmt->execute();
        
        echo "\nStatus updated to 1 (active)\n";
        
        // Verify
        $stmt = $db->query("SELECT id, username, status FROM users WHERE username = 'admin'");
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "\nUpdated admin user:\n";
        print_r($user);
    } else {
        echo "Admin user not found. Run setup_admin.php first.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

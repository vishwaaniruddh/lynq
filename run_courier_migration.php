<?php
/**
 * Run Courier Table Migration
 * Creates the couriers table for the Courier Master module
 */

require_once __DIR__ . '/config/autoload.php';

try {
    echo "Running Courier Table Migration...\n";
    echo "==================================\n\n";
    
    require_once __DIR__ . '/migrations/2024_12_28_600000_create_couriers_table.php';
    
    $migration = new CreateCouriersTable();
    $migration->up();
    
    echo "Couriers table created successfully!\n";
    
    // Mark migration as executed
    $db = DatabaseConfig::getInstance()->getConnection();
    $migrationName = '2024_12_28_600000_create_couriers_table';
    
    // Create migrations table if not exists
    $db->query("CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_migration (migration)
    )");
    
    $stmt = $db->prepare("INSERT IGNORE INTO migrations (migration) VALUES (?)");
    $stmt->bind_param('s', $migrationName);
    $stmt->execute();
    $stmt->close();
    
    echo "Migration marked as executed.\n";
    
} catch (Exception $e) {
    echo "\nMigration failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}

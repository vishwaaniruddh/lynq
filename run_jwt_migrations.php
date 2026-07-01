<?php
/**
 * Run JWT Authentication Migrations
 * 
 * This script runs the migrations for JWT authentication tables:
 * - refresh_tokens
 * - token_blacklist
 * 
 * Usage: php run_jwt_migrations.php [up|down]
 */

require_once __DIR__ . '/config/database.php';

echo "=== JWT Authentication Migrations ===\n\n";

// Include migration files
require_once __DIR__ . '/migrations/2026_01_01_100000_create_refresh_tokens_table.php';
require_once __DIR__ . '/migrations/2026_01_01_100001_create_token_blacklist_table.php';

$action = $argv[1] ?? 'up';

try {
    if ($action === 'down') {
        echo "Rolling back JWT migrations...\n\n";
        
        // Rollback in reverse order
        $blacklistMigration = new CreateTokenBlacklistTable();
        $blacklistMigration->down();
        
        $refreshTokenMigration = new CreateRefreshTokensTable();
        $refreshTokenMigration->down();
        
        echo "\nJWT migrations rolled back successfully.\n";
    } else {
        echo "Running JWT migrations...\n\n";
        
        // Run in order
        $refreshTokenMigration = new CreateRefreshTokensTable();
        $refreshTokenMigration->up();
        
        $blacklistMigration = new CreateTokenBlacklistTable();
        $blacklistMigration->up();
        
        echo "\nJWT migrations completed successfully.\n";
    }
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

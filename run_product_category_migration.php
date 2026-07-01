<?php
/**
 * Run Product Category Permissions Migration
 * 
 * This script adds the product category master permissions to the database.
 * Run this once to set up the permissions.
 */

require_once __DIR__ . '/migrations/2024_12_30_000001_add_product_category_permissions.php';

echo "=== Product Category Permissions Migration ===\n\n";

$migration = new AddProductCategoryPermissionsMigration();
$migration->up();

echo "\n=== Migration Complete ===\n";

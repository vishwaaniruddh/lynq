<?php
/**
 * Seed Dummy Stock Data
 * 
 * Creates sample stock entries for existing products in warehouses
 * Run: php scripts/seed_dummy_stock.php
 */

require_once __DIR__ . '/../config/autoload.php';

echo "=== Seeding Dummy Stock Data ===\n\n";

$db = DatabaseConfig::getInstance();

// Get existing products
$products = $db->getResults("SELECT id, name, is_serializable, is_repairable FROM products WHERE status = 'active' LIMIT 20");

if (empty($products)) {
    echo "No active products found. Creating sample products first...\n";
    
    // Create sample product categories if not exist
    $categoryCheck = $db->getResults("SELECT id FROM product_categories LIMIT 1");
    if (empty($categoryCheck)) {
        $db->executeQuery("INSERT INTO product_categories (name, description, status) VALUES 
            ('Network Equipment', 'Routers, switches, and network devices', 'active'),
            ('Cables & Accessories', 'Network cables and accessories', 'active'),
            ('Power Equipment', 'UPS, batteries, and power supplies', 'active')
        ");
        echo "Created sample product categories.\n";
    }
    
    $categories = $db->getResults("SELECT id FROM product_categories WHERE status = 'active' LIMIT 3");
    $catIds = array_column($categories, 'id');
    
    // Create sample products
    $sampleProducts = [
        ['Router Model A', $catIds[0] ?? 1, 1, 1, 'INTERNAL'],
        ['Switch 24-Port', $catIds[0] ?? 1, 1, 1, 'INTERNAL'],
        ['CAT6 Cable 1m', $catIds[1] ?? 1, 0, 0, 'SITE'],
        ['CAT6 Cable 3m', $catIds[1] ?? 1, 0, 0, 'SITE'],
        ['Fiber Patch Cord', $catIds[1] ?? 1, 0, 0, 'SITE'],
        ['UPS 1KVA', $catIds[2] ?? 1, 1, 1, 'INTERNAL'],
        ['Battery 12V', $catIds[2] ?? 1, 1, 1, 'INTERNAL'],
        ['Power Adapter', $catIds[2] ?? 1, 0, 0, 'SITE'],
        ['ONT Device', $catIds[0] ?? 1, 1, 1, 'SITE'],
        ['Media Converter', $catIds[0] ?? 1, 1, 1, 'INTERNAL']
    ];
    
    foreach ($sampleProducts as $p) {
        $db->executeQuery(
            "INSERT INTO products (name, category_id, is_serializable, is_repairable, inventory_type, unit_of_measure, status, created_at) 
             VALUES (?, ?, ?, ?, ?, 'piece', 'active', NOW())",
            [$p[0], $p[1], $p[2], $p[3], $p[4]],
            'siiss'
        );
    }
    echo "Created " . count($sampleProducts) . " sample products.\n";
    
    // Reload products
    $products = $db->getResults("SELECT id, name, is_serializable, is_repairable FROM products WHERE status = 'active' LIMIT 20");
}

echo "Found " . count($products) . " products.\n";

// Get existing warehouses
$warehouses = $db->getResults("SELECT id, name FROM warehouses WHERE status = 'active' LIMIT 5");

if (empty($warehouses)) {
    echo "No active warehouses found. Creating sample warehouses...\n";
    
    // Get ADV company
    $advCompany = $db->getResults("SELECT id FROM companies WHERE type = 'adv' LIMIT 1");
    $companyId = $advCompany[0]['id'] ?? 1;
    
    $sampleWarehouses = [
        ['Main Warehouse', 'Central storage facility', $companyId],
        ['Regional Warehouse North', 'Northern region storage', $companyId],
        ['Regional Warehouse South', 'Southern region storage', $companyId]
    ];
    
    foreach ($sampleWarehouses as $w) {
        $db->executeQuery(
            "INSERT INTO warehouses (name, description, company_id, status, created_at) VALUES (?, ?, ?, 'active', NOW())",
            [$w[0], $w[1], $w[2]],
            'ssi'
        );
    }
    echo "Created " . count($sampleWarehouses) . " sample warehouses.\n";
    
    $warehouses = $db->getResults("SELECT id, name FROM warehouses WHERE status = 'active' LIMIT 5");
}

echo "Found " . count($warehouses) . " warehouses.\n\n";

// Seed stock data
$stockCreated = 0;
$assetsCreated = 0;

foreach ($products as $product) {
    foreach ($warehouses as $warehouse) {
        if ($product['is_serializable']) {
            // Create serializable assets with serial numbers
            $assetCount = rand(3, 8);
            for ($i = 1; $i <= $assetCount; $i++) {
                $serialNumber = strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $product['name']), 0, 4)) 
                    . '-' . $warehouse['id'] 
                    . '-' . str_pad($i, 4, '0', STR_PAD_LEFT)
                    . '-' . substr(md5(uniqid()), 0, 4);
                
                // Check if serial number exists
                $exists = $db->getResults("SELECT id FROM assets WHERE serial_number = ?", [$serialNumber], 's');
                if (!empty($exists)) {
                    continue;
                }
                
                $warrantyExpiry = date('Y-m-d', strtotime('+' . rand(6, 24) . ' months'));
                $workingCondition = rand(1, 10) > 1 ? 'working' : 'not_working';
                
                $db->executeQuery(
                    "INSERT INTO assets (product_id, warehouse_id, serial_number, status, working_condition, 
                     source_warehouse_id, warranty_expiry, notes, created_at) 
                     VALUES (?, ?, ?, 'in_stock', ?, ?, ?, 'Auto-generated dummy data', NOW())",
                    [$product['id'], $warehouse['id'], $serialNumber, $workingCondition, $warehouse['id'], $warrantyExpiry],
                    'iissss'
                );
                $assetsCreated++;
            }
        } else {
            // Create non-serializable stock entry
            $quantity = rand(10, 100);
            
            // Check if stock entry exists
            $exists = $db->getResults(
                "SELECT id FROM stock WHERE product_id = ? AND warehouse_id = ?",
                [$product['id'], $warehouse['id']],
                'ii'
            );
            
            if (!empty($exists)) {
                // Update existing stock
                $db->executeQuery(
                    "UPDATE stock SET quantity = quantity + ? WHERE product_id = ? AND warehouse_id = ?",
                    [$quantity, $product['id'], $warehouse['id']],
                    'iii'
                );
            } else {
                // Create new stock entry
                $db->executeQuery(
                    "INSERT INTO stock (product_id, warehouse_id, quantity, reserved_quantity, created_at) 
                     VALUES (?, ?, ?, 0, NOW())",
                    [$product['id'], $warehouse['id'], $quantity],
                    'iii'
                );
            }
            $stockCreated++;
        }
    }
}

echo "=== Summary ===\n";
echo "Stock entries created/updated: $stockCreated\n";
echo "Assets created: $assetsCreated\n";
echo "\nDummy stock data seeded successfully!\n";

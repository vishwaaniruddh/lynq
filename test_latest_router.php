<?php
require_once __DIR__ . '/config/autoload.php';
$db = DatabaseConfig::getInstance();

$sql = "SELECT 
            s.id, s.site_name,
            r_map.router_serial_number,
            r_map.router_ip,
            r_map.network_ip,
            r_map.site_ip,
            r_map.subnet_mask
        FROM sites s
        LEFT JOIN (
            SELECT 
                latest_r.site_id,
                a.serial_number as router_serial_number,
                ip.router_ip,
                ip.network_ip,
                ip.site_ip,
                ip.subnet_mask
            FROM (
                SELECT MAX(di_inner.id) as max_di_id, d_inner.site_id
                FROM dispatches d_inner
                JOIN dispatch_items di_inner ON d_inner.id = di_inner.dispatch_id
                JOIN assets a_inner ON di_inner.asset_id = a_inner.id
                JOIN products p_inner ON a_inner.product_id = p_inner.id
                JOIN product_categories pc_inner ON p_inner.category_id = pc_inner.id
                WHERE pc_inner.name NOT LIKE '%sim%' AND p_inner.is_serializable = 1 AND d_inner.status != 'cancelled'
                GROUP BY d_inner.site_id
            ) latest_r
            JOIN dispatch_items di ON latest_r.max_di_id = di.id
            JOIN dispatches d ON di.dispatch_id = d.id
            JOIN assets a ON di.asset_id = a.id
            LEFT JOIN router_ip_bindings rib ON a.serial_number = rib.router_serial_number AND rib.status = 'active'
            LEFT JOIN ip_master ip ON rib.ip_master_id = ip.id
        ) r_map ON s.id = r_map.site_id
        WHERE s.status != 'deleted'";

$rows = $db->getResults($sql, [], '');
echo "Mapped routers result:\n";
print_r($rows);

-- Database Table Export for `migrations`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `migrations`;

CREATE TABLE `migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_migration` (`migration`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `migrations`
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('1', '2024_12_27_100000_create_core_tables', '2025-12-27 09:45:18');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('2', '2024_12_27_200000_create_company_access_log', '2025-12-27 11:18:43');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('3', '2024_12_28_600000_create_couriers_table', '2025-12-28 11:12:34');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('4', '2024_12_28_100000_create_api_access_log', '2025-12-28 21:23:58');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('5', '2024_12_28_200000_create_security_tables', '2025-12-28 21:23:58');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('6', '2024_12_28_300000_add_system_manage_permission', '2025-12-28 21:24:44');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('7', '2024_12_28_400000_create_master_module_tables', '2025-12-28 21:24:44');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('8', '2024_12_28_500000_add_master_module_permissions', '2025-12-28 21:24:44');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('9', '2024_12_28_700000_add_courier_permissions', '2025-12-28 21:24:44');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('10', '2024_12_28_800000_create_sites_table', '2025-12-28 21:24:44');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('11', '2024_12_28_800001_create_site_delegations_table', '2025-12-28 21:24:44');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('12', '2024_12_28_800002_create_engineer_assignments_table', '2025-12-28 21:24:44');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('13', '2024_12_28_800003_create_delegation_history_table', '2025-12-28 21:24:44');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('14', '2024_12_28_900000_add_site_management_permissions', '2025-12-28 21:24:45');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('15', '2024_12_29_100000_create_warehouses_table', '2025-12-28 21:24:45');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('16', '2024_12_29_200000_create_products_tables', '2025-12-28 21:24:45');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('17', '2024_12_29_300000_create_stock_assets_tables', '2025-12-28 21:24:45');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('18', '2024_12_29_400000_create_dispatches_tables', '2025-12-28 21:24:45');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('19', '2024_12_29_500000_create_transfers_tables', '2025-12-28 21:24:45');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('20', '2024_12_29_600000_create_repairs_table', '2025-12-28 21:24:45');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('21', '2024_12_29_700000_create_alerts_audit_tables', '2025-12-28 21:24:45');
INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES ('22', '2024_12_29_800000_add_inventory_permissions', '2025-12-28 21:24:45');

SET FOREIGN_KEY_CHECKS = 1;

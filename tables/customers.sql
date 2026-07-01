-- Database Table Export for `customers`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `customers`;

CREATE TABLE `customers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'India',
  `postal_code` varchar(20) DEFAULT NULL,
  `country_id` int(11) DEFAULT NULL,
  `state_id` int(11) DEFAULT NULL,
  `city_id` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_customer_email` (`email`),
  KEY `idx_status` (`status`),
  KEY `idx_name` (`name`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_country_id` (`country_id`),
  KEY `idx_state_id` (`state_id`),
  KEY `idx_city_id` (`city_id`),
  CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `customers`
INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `city`, `state`, `country`, `postal_code`, `country_id`, `state_id`, `city_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('1', 'Euronet', 'euronet@euronet.com', '9090901010', 'wadala truck terminal', 'Mumbai', 'Maharashtra', 'India', '400057', NULL, NULL, NULL, '1', '2025-12-29 21:00:10', '2025-12-29 21:40:31', '2326', '2326');
INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `city`, `state`, `country`, `postal_code`, `country_id`, `state_id`, `city_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('2', 'Hitachi', 'hitachi@hitachi.com', '8787878787', 'Thane', 'Thane', 'Maharashtra', 'India', '400612', NULL, NULL, NULL, '1', '2025-12-29 21:45:18', '2025-12-29 21:45:18', '2326', NULL);
INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `city`, `state`, `country`, `postal_code`, `country_id`, `state_id`, `city_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES ('3', 'Diebold', 'diebold@diebold.com', '768770087', '293, Elphinstone road , Naupada Thane', 'Thane', 'Maharashtra', 'India', '400454', NULL, NULL, NULL, '1', '2026-01-04 10:46:34', '2026-01-04 10:46:34', '2326', NULL);

SET FOREIGN_KEY_CHECKS = 1;

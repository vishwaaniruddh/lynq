-- Database Table Export for `dispatches`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `dispatches`;

CREATE TABLE `dispatches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dispatch_number` varchar(50) NOT NULL,
  `sender_type` enum('warehouse','company','user') DEFAULT 'warehouse',
  `sender_id` int(11) DEFAULT NULL,
  `from_company_id` int(11) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_company_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `to_warehouse_id` int(11) DEFAULT NULL,
  `site_id` int(11) DEFAULT NULL,
  `material_request_id` int(11) DEFAULT NULL,
  `dispatch_date` date NOT NULL,
  `status` enum('pending','in_transit','delivered','cancelled') DEFAULT 'pending',
  `acknowledgment_status` enum('pending','acknowledged') DEFAULT 'pending',
  `receive_status` enum('pending','accepted','rejected','partial') DEFAULT 'pending',
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `acknowledged_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `courier_id` int(11) DEFAULT NULL,
  `pod_number` varchar(100) DEFAULT NULL,
  `contact_person_name` varchar(255) DEFAULT NULL,
  `contact_person_phone` varchar(50) DEFAULT NULL,
  `lr_copy_path` varchar(500) DEFAULT NULL,
  `pod_receipt_path` varchar(500) DEFAULT NULL,
  `acknowledgment_notes` text DEFAULT NULL,
  `acknowledgment_condition` enum('good','minor_damage','damaged','missing') DEFAULT 'good',
  `acknowledgment_proof` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`acknowledgment_proof`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_dispatch_number` (`dispatch_number`),
  KEY `acknowledged_by` (`acknowledged_by`),
  KEY `created_by` (`created_by`),
  KEY `idx_from_company_id` (`from_company_id`),
  KEY `idx_from_warehouse_id` (`from_warehouse_id`),
  KEY `idx_to_company_id` (`to_company_id`),
  KEY `idx_to_user_id` (`to_user_id`),
  KEY `idx_to_warehouse_id` (`to_warehouse_id`),
  KEY `idx_status` (`status`),
  KEY `idx_acknowledgment_status` (`acknowledgment_status`),
  KEY `idx_dispatch_date` (`dispatch_date`),
  KEY `idx_receive_status` (`receive_status`),
  KEY `idx_site_id` (`site_id`),
  KEY `idx_material_request_id` (`material_request_id`),
  CONSTRAINT `dispatches_ibfk_1` FOREIGN KEY (`from_company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `dispatches_ibfk_2` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  CONSTRAINT `dispatches_ibfk_3` FOREIGN KEY (`to_company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dispatches_ibfk_4` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dispatches_ibfk_5` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dispatches_ibfk_6` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `dispatches_ibfk_7` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1585 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `dispatches`
INSERT INTO `dispatches` (`id`, `dispatch_number`, `sender_type`, `sender_id`, `from_company_id`, `from_warehouse_id`, `to_company_id`, `to_user_id`, `to_warehouse_id`, `site_id`, `material_request_id`, `dispatch_date`, `status`, `acknowledgment_status`, `receive_status`, `acknowledged_at`, `acknowledged_by`, `notes`, `created_by`, `created_at`, `updated_at`, `courier_id`, `pod_number`, `contact_person_name`, `contact_person_phone`, `lr_copy_path`, `pod_receipt_path`, `acknowledgment_notes`, `acknowledgment_condition`, `acknowledgment_proof`) VALUES ('1584', 'DSP-20260105-12DDCA', 'warehouse', NULL, '1', '2', '2', NULL, NULL, '1', '3', '2026-01-05', 'delivered', 'acknowledged', 'pending', '2026-01-05 03:39:10', '19330', 'Material Request #3 for site: Sample Site 1', '2326', '2026-01-05 03:37:05', '2026-01-05 03:39:10', '2', 'Blue67744', 'Andi', '67854447', NULL, NULL, NULL, 'good', NULL);

SET FOREIGN_KEY_CHECKS = 1;

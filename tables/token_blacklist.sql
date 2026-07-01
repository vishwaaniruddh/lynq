-- Database Table Export for `token_blacklist`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `token_blacklist`;

CREATE TABLE `token_blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token_jti` varchar(64) NOT NULL COMMENT 'Token ID (jti claim) of blacklisted token',
  `expires_at` datetime NOT NULL COMMENT 'When the token would naturally expire',
  `created_at` datetime DEFAULT current_timestamp() COMMENT 'When token was blacklisted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token_jti` (`token_jti`),
  KEY `idx_token_jti` (`token_jti`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=11558 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores blacklisted JWT access tokens for immediate invalidation';

-- Dumping data for table `token_blacklist`
INSERT INTO `token_blacklist` (`id`, `token_jti`, `expires_at`, `created_at`) VALUES ('11552', '106ce8133a70b663b7114867b30f6e95', '2026-01-05 23:43:18', '2026-01-05 23:28:44');
INSERT INTO `token_blacklist` (`id`, `token_jti`, `expires_at`, `created_at`) VALUES ('11553', 'e3be0336d9d0aad216ed356300fd3871', '2026-01-06 00:00:19', '2026-01-05 23:32:04');
INSERT INTO `token_blacklist` (`id`, `token_jti`, `expires_at`, `created_at`) VALUES ('11554', '62150c7ebdb6f55554bcc99867ca2388', '2026-01-06 13:59:05', '2026-01-06 13:29:55');
INSERT INTO `token_blacklist` (`id`, `token_jti`, `expires_at`, `created_at`) VALUES ('11555', '4fd4d492d2b060ffd16acf968d381240', '2026-01-06 14:00:11', '2026-01-06 13:36:30');
INSERT INTO `token_blacklist` (`id`, `token_jti`, `expires_at`, `created_at`) VALUES ('11556', 'b9cf841b9babc253aa41b881ec064ca6', '2026-01-06 14:06:45', '2026-01-06 13:37:44');
INSERT INTO `token_blacklist` (`id`, `token_jti`, `expires_at`, `created_at`) VALUES ('11557', '8df59a9fa01ba13d377f91376151b2f8', '2026-03-20 22:31:32', '2026-03-20 09:33:00');

SET FOREIGN_KEY_CHECKS = 1;

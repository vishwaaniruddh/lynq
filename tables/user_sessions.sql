-- Database Table Export for `user_sessions`
-- Generated: 2026-06-25 07:00:57
-- DB Name: if0_40845939_clarity_db

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `user_sessions`;

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_session_token` (`session_token`),
  KEY `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=680 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `user_sessions`
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('638', '2326', '8c317e8138401a40adf89d44d3118441d32ec195bf3952f9e877d8d18a2bf21c', '152.58.42.62', NULL, '2026-01-05 17:45:24', '2026-01-05 16:45:24', '2026-01-05 16:45:24');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('640', '2326', '33c90d26388897903f792a0a97b795a320bba148b5871be6ffcbb31feba0fa66', '::1', NULL, '2026-01-05 18:59:05', '2026-01-05 17:59:05', '2026-01-05 17:59:05');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('641', '2326', '78aaaad97a0e83b60722ba38ea40d059833c379c9fc9867fd9f471dc35f3db91', '152.58.29.165', NULL, '2026-01-05 19:00:01', '2026-01-05 18:00:01', '2026-01-05 18:00:01');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('644', '2326', 'a540f2b02ee0a555b5901ebe53eeb5979eb10240d89d595a3fe3da0383cb4af0', '192.168.18.12', NULL, '2026-01-05 19:11:27', '2026-01-05 18:11:27', '2026-01-05 18:11:27');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('645', '2326', 'd3f8d89f57a3726b1c3dcfc2b0e20b2cc780eca2184faf3aa46f4797e58036a6', '27.107.226.38', NULL, '2026-01-06 07:00:13', '2026-01-06 06:00:13', '2026-01-06 06:00:13');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('649', '2326', '8790a04dd8101c451599066d5388260688f9c77ba3846e135c46f9869f7b94cf', '152.58.29.112', NULL, '2026-01-06 09:08:00', '2026-01-06 08:08:00', '2026-01-06 08:08:00');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('650', '2326', 'dabb1aea1ee5a0a8af1651f3f35d180a3d5895f4c0aca246e8b7f2c1ed6a0294', '192.168.18.12', NULL, '2026-01-06 09:17:09', '2026-01-06 08:17:09', '2026-01-06 08:17:09');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('651', '2326', '55c3b44fb3ff5cd80f58559062949a35bcb532ba60f49e5b06f9f77389eb79d0', '192.168.18.12', NULL, '2026-01-06 13:22:21', '2026-01-06 12:22:21', '2026-01-06 12:22:21');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('652', '2326', 'a3242b0c2fdefafc04c8c20bdd56826672c763cf6fc1b289baa965f0e45719c1', '152.58.0.118', NULL, '2026-01-06 13:56:51', '2026-01-06 12:56:51', '2026-01-06 12:56:51');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('653', '2326', 'c761d149813e314cb829352d034415cd4cf15ffcc3396eba82068c55a5cbdf51', '152.58.0.140', NULL, '2026-01-06 16:12:08', '2026-01-06 15:12:08', '2026-01-06 15:12:08');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('654', '2326', '98e43a4b092d0ff8f071c96d3a38dde0589f3f223edd6992befc036d6bf28d0e', '106.221.216.234', NULL, '2026-01-15 07:37:33', '2026-01-15 06:37:33', '2026-01-15 06:37:33');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('655', '22032', 'e39b72c62b04e61c6dd0ba2f14579f758b5ddf641add4061676c0c5fa7f0179c', '122.179.131.36', NULL, '2026-01-16 07:02:26', '2026-01-16 06:02:26', '2026-01-16 06:02:26');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('657', '2326', '7ce84818e18dc21dad909b7c848d3d5dc8af43b00da58a5f79b6ba8c4600d1d2', '152.58.33.89', NULL, '2026-01-17 03:25:01', '2026-01-17 02:25:01', '2026-01-17 02:25:01');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('658', '2326', '14b78f3d335213d48218c81d450a6edd17d670342637588ff1a640f112c806a0', '49.204.165.8', NULL, '2026-01-18 12:09:36', '2026-01-18 11:09:36', '2026-01-18 11:09:36');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('659', '2326', '736b1bf278cb3f118e6762b6cddb4829e0498243be775a0e7cc9ea904a40689c', '49.204.165.71', NULL, '2026-01-24 18:17:18', '2026-01-24 17:17:18', '2026-01-24 17:17:18');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('660', '2326', 'e067d17fda3366b016d0a69d40a140a65072a53c06f7477bac648634f04adc19', '49.204.164.153', NULL, '2026-02-02 12:07:16', '2026-02-02 11:07:16', '2026-02-02 11:07:16');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('662', '2326', '168b6d3fc72b6ce206deb90c26828f48fd3bc1b46f8303e7a3dd174b8e180637', '49.204.164.153', NULL, '2026-02-03 14:49:27', '2026-02-03 13:49:27', '2026-02-03 13:49:27');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('663', '2326', 'e926ade5c9492698f7f9b7059be9d562d55123cc5002d7ac373dcd6ae1bad50e', '49.204.164.153', NULL, '2026-02-03 14:53:28', '2026-02-03 13:53:28', '2026-02-03 13:53:28');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('665', '2326', 'e67a759ffff5980b1ea71c765dc2787c48c6f6e0b31cc7a1b440bc1df59859c2', '49.204.164.153', NULL, '2026-02-04 11:41:12', '2026-02-04 10:41:12', '2026-02-04 10:41:12');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('666', '2326', 'a3916a97acfec11c84527130ed1613767e10aae37f295ee65397f8c52f714446', '49.204.164.153', NULL, '2026-02-04 23:30:37', '2026-02-04 22:30:37', '2026-02-04 22:30:37');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('667', '2326', 'a9eb0db8a1689914ef792ca755520075acd6fc4781aa12492ab03084dfbe2a90', '49.204.164.153', NULL, '2026-02-05 04:31:54', '2026-02-05 03:31:54', '2026-02-05 03:31:54');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('668', '2326', 'd967927dd9c37d1065f4f4099fda6e6928a490d039cf707c21ad09e3fb08ccd4', '49.204.164.75', NULL, '2026-02-06 12:08:37', '2026-02-06 11:08:37', '2026-02-06 11:08:37');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('669', '2326', '37005df7358bcd7117f97c4ca64d6b72297a9efaae605facf6a28b4d4e462449', '106.215.183.60', NULL, '2026-02-09 05:58:50', '2026-02-09 04:58:50', '2026-02-09 04:58:50');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('670', '2326', '0ceeff04a28f6c06fc2a1d405bbc16ea8ae891bef0c9fc9c49d6361176e751ce', '49.204.165.45', NULL, '2026-03-02 01:31:53', '2026-03-02 00:31:53', '2026-03-02 00:31:53');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('672', '2326', 'd5a171aec20c34e60c7b8b56bf159b0e0467486efdb3373058579cb5456a2fb0', '49.204.164.56', NULL, '2026-03-21 05:32:29', '2026-03-21 04:32:29', '2026-03-21 04:32:29');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('673', '2326', 'fe92ba723cb23e8d6bf351744d6da9b0b6408ec1b2abc7457fa4cc9b65bb2146', '49.204.164.246', NULL, '2026-03-23 00:11:59', '2026-03-22 23:11:59', '2026-03-22 23:11:59');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('674', '2326', 'f86b238e7fa742246974d2355b569738a57c13fc55a1c6827aad496d33ba1a3e', '106.219.57.31', NULL, '2026-04-01 14:04:45', '2026-04-01 13:04:45', '2026-04-01 13:04:45');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('675', '2326', '60b585408aa47c0f23131f4acc358738509d3e2b789cc64af5b9047f0caa1744', '49.204.164.189', NULL, '2026-04-04 08:37:40', '2026-04-04 07:37:40', '2026-04-04 07:37:40');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('676', '2326', '974f912466e122fb4321fda87d5e105b1fc4e6a20dc77e6a490116cb0fc19123', '49.204.165.56', NULL, '2026-04-08 01:46:44', '2026-04-08 00:46:44', '2026-04-08 00:46:44');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('677', '2326', 'd7bb13acf8382c27c966b90e4adc587b508e2f643e19efa0a7ecbd8b48d51de0', '106.215.178.71', NULL, '2026-04-11 02:22:06', '2026-04-11 01:22:06', '2026-04-11 01:22:06');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('678', '2326', 'a6f3823dc8823fa255de752c821c52055d2505d3f636c52f508ddfe8b532cd50', '49.204.165.129', NULL, '2026-05-14 01:19:20', '2026-05-14 00:19:20', '2026-05-14 00:19:20');
INSERT INTO `user_sessions` (`id`, `user_id`, `session_token`, `ip_address`, `user_agent`, `expires_at`, `created_at`, `last_activity`) VALUES ('679', '2326', '08a90791b7c6e4a8372ef028351e639ed34fa3bfe9f268d87b48825b6f026d11', '49.204.165.220', NULL, '2026-06-22 22:59:30', '2026-06-22 21:59:30', '2026-06-22 21:59:30');

SET FOREIGN_KEY_CHECKS = 1;

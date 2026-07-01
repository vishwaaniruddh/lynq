-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 31, 2025 at 04:06 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `clarity_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `api_access_log`
--

CREATE TABLE `api_access_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `endpoint` varchar(255) NOT NULL,
  `method` varchar(10) NOT NULL,
  `params` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `response_code` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `api_access_log`
--

INSERT INTO `api_access_log` (`id`, `user_id`, `endpoint`, `method`, `params`, `ip_address`, `user_agent`, `response_code`, `created_at`) VALUES
(1, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '106.215.177.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-31 14:59:54'),
(2, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', NULL, '2025-12-31 15:00:43'),
(3, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:00:43'),
(4, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:00:46'),
(5, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '106.215.177.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-31 15:00:54'),
(6, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', NULL, '2025-12-31 15:01:43'),
(7, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:01:44'),
(8, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:01:46'),
(9, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '106.215.177.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-31 15:01:54'),
(10, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', NULL, '2025-12-31 15:02:43'),
(11, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:02:46'),
(12, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:02:50'),
(13, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '106.215.177.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-31 15:02:54'),
(14, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', NULL, '2025-12-31 15:03:43'),
(15, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:03:46'),
(16, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:03:50'),
(17, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '106.215.177.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-31 15:03:54'),
(18, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', NULL, '2025-12-31 15:04:43'),
(19, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:04:46'),
(20, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:04:50'),
(21, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '106.215.177.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-31 15:04:54'),
(22, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36 Edg/143.0.0.0', NULL, '2025-12-31 15:05:43'),
(23, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:05:46'),
(24, 2326, '/api/feasibility/tracking', 'GET', '{\"filters\":{\"page\":1,\"limit\":20}}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:05:48'),
(25, 19330, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19330,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:05:51'),
(26, 19331, '/api/inventory/receive/pending', 'GET', '{\"entity_type\":\"user\",\"entity_id\":19331,\"status\":\"pending\",\"from_date\":null,\"to_date\":null,\"count_only\":true,\"result_count\":0}', '106.215.177.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', NULL, '2025-12-31 15:05:54'),
(27, 2326, '/api/sites', 'GET', '{\"search\":null,\"status\":null,\"lho\":null,\"page\":1}', '192.168.18.12', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:146.0) Gecko/20100101 Firefox/146.0', NULL, '2025-12-31 15:06:02');

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serial_number` varchar(100) NOT NULL,
  `warehouse_id` int(11) DEFAULT NULL,
  `status` enum('in_stock','dispatched','assigned','in_use','returned','under_repair','scrapped','lost') DEFAULT 'in_stock',
  `working_condition` enum('working','not_working') DEFAULT 'working',
  `current_holder_type` enum('warehouse','company','user') DEFAULT 'warehouse',
  `current_holder_id` int(11) DEFAULT NULL,
  `source_warehouse_id` int(11) DEFAULT NULL COMMENT 'Original warehouse where asset was first added',
  `warranty_expiry` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `banks`
--

CREATE TABLE `banks` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `banks`
--

INSERT INTO `banks` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'HDFC Bank', 1, '2025-12-29 20:45:33', '2025-12-29 20:45:33', 2326, NULL),
(2, 'SBI', 1, '2025-12-29 20:45:40', '2025-12-29 20:45:40', 2326, NULL),
(3, 'Axis Bank', 1, '2025-12-29 20:45:47', '2025-12-29 20:46:24', 2326, 2326),
(4, 'IDFC Bank', 1, '2025-12-29 20:45:56', '2025-12-29 20:45:56', 2326, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cities`
--

CREATE TABLE `cities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `state_id` int(11) NOT NULL,
  `country_id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cities`
--

INSERT INTO `cities` (`id`, `name`, `state_id`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Bilaspur', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(2, 'Chamba', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(3, 'Hamirpur', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(4, 'Kangra', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(5, 'Kinnaur', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(6, 'Kullu', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(7, 'Lahaul and Spiti', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(8, 'Mandi', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(9, 'Shimla', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(10, 'Sirmaur', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(11, 'Solan', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(12, 'Una', 1, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(13, 'Amritsar', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(14, 'Barnala', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(15, 'Bathinda', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(16, 'Faridkot', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(17, 'Fatehgarh Sahib', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(18, 'Fazilka', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(19, 'Ferozepur', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(20, 'Gurdaspur', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(21, 'Hoshiarpur', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(22, 'Jalandhar', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(23, 'Kapurthala', 2, 1, 1, 'active', '2025-07-02 07:18:26', '2025-07-02 07:18:26', NULL, NULL),
(24, 'Ludhiana', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(25, 'Mansa', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(26, 'Moga', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(27, 'Muktsar', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(28, 'Pathankot', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(29, 'Patiala', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(30, 'Rupnagar', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(31, 'Sangrur', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(32, 'Shaheed Bhagat Singh Nagar (Nawanshahr)', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(33, 'Sri Muktsar Sahib', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(34, 'Tarn Taran', 2, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(35, 'Ambala', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(36, 'Bhiwani', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(37, 'Charkhi Dadri', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(38, 'Faridabad', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(39, 'Fatehabad', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(40, 'Gurugram', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(41, 'Hisar', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(42, 'Jhajjar', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(43, 'Jind', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(44, 'Kaithal', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(45, 'Karnal', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(46, 'Kurukshetra', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(47, 'Mahendragarh', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(48, 'Nuh', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(49, 'Palwal', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(50, 'Panchkula', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(51, 'Panipat', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(52, 'Rewari', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(53, 'Rohtak', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(54, 'Sirsa', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(55, 'Sonipat', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(56, 'Yamunanagar', 3, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(57, 'Ajmer', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(58, 'Alwar', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(59, 'Balotra', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(60, 'Banswara', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(61, 'Baran', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(62, 'Barmer', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(63, 'Beawar', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(64, 'Bharatpur', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(65, 'Bhilwara', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(66, 'Bikaner', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(67, 'Bundi', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(68, 'Chittorgarh', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(69, 'Churu', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(70, 'Dausa', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(71, 'Deeg', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(72, 'Didwana-Kuchaman', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(73, 'Dholpur', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(74, 'Dungarpur', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(75, 'Ganganagar', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(76, 'Gangapur City', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(77, 'Hanumangarh', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(78, 'Jaipur', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(79, 'Jaipur Rural', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(80, 'Jaisalmer', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(81, 'Jalore', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(82, 'Jhalawar', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(83, 'Jhunjhunu', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(84, 'Jodhpur', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(85, 'Jodhpur Rural', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(86, 'Karauli', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(87, 'Kekri', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(88, 'Khairthal-Tijara', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(89, 'Kota', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(90, 'Kotputli-Behror', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(91, 'Nagaur', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(92, 'Pali', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(93, 'Phalodi', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(94, 'Pratapgarh', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(95, 'Rajsamand', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(96, 'Salumbar', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(97, 'Sanchore', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(98, 'Sawai Madhopur', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(99, 'Sikar', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(100, 'Sirohi', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(101, 'Shahpura', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(102, 'Tonk', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(103, 'Udaipur', 4, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(104, 'Agra', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(105, 'Aligarh', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(106, 'Ambedkar Nagar', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(107, 'Amethi', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(108, 'Amroha', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(109, 'Auraiya', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(110, 'Ayodhya', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(111, 'Azamgarh', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(112, 'Baghpat', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(113, 'Bahraich', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(114, 'Ballia', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(115, 'Balrampur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(116, 'Banda', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(117, 'Barabanki', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(118, 'Bareilly', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(119, 'Basti', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(120, 'Bhadohi', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(121, 'Bijnor', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(122, 'Budaun', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(123, 'Bulandshahr', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(124, 'Chandauli', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(125, 'Chitrakoot', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(126, 'Deoria', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(127, 'Etah', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(128, 'Etawah', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(129, 'Farrukhabad', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(130, 'Fatehpur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(131, 'Firozabad', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(132, 'Gautam Buddha Nagar', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(133, 'Ghaziabad', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(134, 'Ghazipur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(135, 'Gonda', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(136, 'Gorakhpur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(137, 'Hamirpur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(138, 'Hapur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(139, 'Hardoi', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(140, 'Hathras', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(141, 'Jalaun', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(142, 'Jaunpur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(143, 'Jhansi', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(144, 'Kannauj', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(145, 'Kanpur Dehat', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(146, 'Kanpur Nagar', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(147, 'Kasganj', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(148, 'Kaushambi', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(149, 'Kushinagar', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(150, 'Lakhimpur Kheri', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(151, 'Lalitpur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(152, 'Lucknow', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(153, 'Maharajganj', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(154, 'Mahoba', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(155, 'Mainpuri', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(156, 'Mathura', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(157, 'Mau', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(158, 'Meerut', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(159, 'Mirzapur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(160, 'Moradabad', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(161, 'Muzaffarnagar', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(162, 'Pilibhit', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(163, 'Pratapgarh', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(164, 'Prayagraj', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(165, 'Rae Bareli', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(166, 'Rampur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(167, 'Saharanpur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(168, 'Sambhal', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(169, 'Sant Kabir Nagar', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(170, 'Sant Ravidas Nagar (Bhadohi)', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(171, 'Shahjahanpur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(172, 'Shamli', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(173, 'Shravasti', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(174, 'Siddharthnagar', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(175, 'Sitapur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(176, 'Sonbhadra', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(177, 'Sultanpur', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(178, 'Unnao', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(179, 'Varanasi', 5, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(180, 'Almora', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(181, 'Bageshwar', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(182, 'Chamoli', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(183, 'Champawat', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(184, 'Dehradun', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(185, 'Haridwar', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(186, 'Nainital', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(187, 'Pauri Garhwal', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(188, 'Pithoragarh', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(189, 'Rudraprayag', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(190, 'Tehri Garhwal', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(191, 'Udham Singh Nagar', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(192, 'Uttarkashi', 6, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(193, 'Anantnag', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(194, 'Bandipora', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(195, 'Baramulla', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(196, 'Budgam', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(197, 'Doda', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(198, 'Ganderbal', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(199, 'Jammu', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(200, 'Kathua', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(201, 'Kishtwar', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(202, 'Kulgam', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(203, 'Kupwara', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(204, 'Poonch', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(205, 'Pulwama', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(206, 'Rajouri', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(207, 'Ramban', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(208, 'Reasi', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(209, 'Samba', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(210, 'Shopian', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(211, 'Udhampur', 7, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(212, 'Kargil', 8, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(213, 'Leh', 8, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(214, 'Chandigarh', 9, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(215, 'Central Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(216, 'East Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(217, 'New Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(218, 'North Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(219, 'North East Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(220, 'North West Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(221, 'Shahdara', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(222, 'South Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(223, 'South East Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(224, 'South West Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(225, 'West Delhi', 10, 1, 1, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(226, 'Alluri Sitharama Raju', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(227, 'Anakapalli', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(228, 'Anantapur', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(229, 'Annamayya', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(230, 'Bapatla', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(231, 'Chittoor', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(232, 'Dr. B.R. Ambedkar Konaseema', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(233, 'East Godavari', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(234, 'Eluru', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(235, 'Guntur', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(236, 'Kakinada', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(237, 'Krishna', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(238, 'Kurnool', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(239, 'Nandyal', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(240, 'Nellore', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(241, 'NTR', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(242, 'Palnadu', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(243, 'Parvathipuram Manyam', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(244, 'Prakasam', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(245, 'Srikakulam', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(246, 'Sri Sathya Sai', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(247, 'Tirupati', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(248, 'Visakhapatnam', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(249, 'Vizianagaram', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(250, 'West Godavari', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(251, 'YSR Kadapa', 11, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(252, 'Bagalkot', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(253, 'Ballari', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(254, 'Belagavi', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(255, 'Bengaluru Rural', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(256, 'Bengaluru Urban', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(257, 'Bidar', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(258, 'Chamarajanagar', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(259, 'Chikkaballapur', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(260, 'Chikkamagaluru', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(261, 'Chitradurga', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(262, 'Dakshina Kannada', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(263, 'Davangere', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(264, 'Dharwad', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(265, 'Gadag', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(266, 'Kalaburagi', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(267, 'Hassan', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(268, 'Haveri', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(269, 'Kodagu', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(270, 'Kolar', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(271, 'Koppal', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(272, 'Mandya', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(273, 'Mysuru', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(274, 'Raichur', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(275, 'Ramanagara', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(276, 'Shivamogga', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(277, 'Tumakuru', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(278, 'Udupi', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(279, 'Uttara Kannada', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(280, 'Vijayapura', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(281, 'Yadgir', 12, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(282, 'Alappuzha', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(283, 'Ernakulam', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(284, 'Idukki', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(285, 'Kannur', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(286, 'Kasaragod', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(287, 'Kollam', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(288, 'Kottayam', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(289, 'Kozhikode', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(290, 'Malappuram', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(291, 'Palakkad', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(292, 'Pathanamthitta', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(293, 'Thiruvananthapuram', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(294, 'Thrissur', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(295, 'Wayanad', 13, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(296, 'Ariyalur', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(297, 'Chengalpattu', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(298, 'Chennai', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(299, 'Coimbatore', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(300, 'Cuddalore', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(301, 'Dharmapuri', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(302, 'Dindigul', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(303, 'Erode', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(304, 'Kallakurichi', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(305, 'Kancheepuram', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(306, 'Kanyakumari', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(307, 'Karur', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(308, 'Krishnagiri', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(309, 'Madurai', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(310, 'Mayiladuthurai', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(311, 'Nagapattinam', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(312, 'Namakkal', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(313, 'Nilgiris', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(314, 'Perambalur', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(315, 'Pudukkottai', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(316, 'Ramanathapuram', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(317, 'Ranipet', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(318, 'Salem', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(319, 'Sivaganga', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(320, 'Tenkasi', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(321, 'Thanjavur', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(322, 'Theni', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(323, 'Thoothukudi', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(324, 'Tiruchirappalli', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(325, 'Tirunelveli', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(326, 'Tirupattur', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(327, 'Tiruppur', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(328, 'Tiruvallur', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(329, 'Tiruvannamalai', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(330, 'Tiruvarur', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(331, 'Vellore', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(332, 'Viluppuram', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(333, 'Virudhunagar', 14, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(334, 'Adilabad', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(335, 'Bhadradri Kothagudem', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(336, 'Hanamkonda', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(337, 'Hyderabad', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(338, 'Jagtial', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(339, 'Jangaon', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(340, 'Jayashankar Bhupalpally', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(341, 'Jogulamba Gadwal', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(342, 'Kamareddy', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(343, 'Karimnagar', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(344, 'Khammam', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(345, 'Komaram Bheem Asifabad', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(346, 'Mahabubabad', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(347, 'Mahabubnagar', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(348, 'Mancherial', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(349, 'Medak', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(350, 'Medchal-Malkajgiri', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(351, 'Mulugu', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(352, 'Nagarkurnool', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(353, 'Nalgonda', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(354, 'Narayanpet', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(355, 'Nirmal', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(356, 'Nizamabad', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(357, 'Peddapalli', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(358, 'Rajanna Sircilla', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(359, 'Rangareddy', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(360, 'Sangareddy', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(361, 'Siddipet', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(362, 'Suryapet', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(363, 'Vikarabad', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(364, 'Wanaparthy', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(365, 'Warangal', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(366, 'Yadadri Bhuvanagiri', 15, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(367, 'Karaikal', 16, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(368, 'Mahe', 16, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(369, 'Puducherry', 16, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(370, 'Yanam', 16, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(371, 'Lakshadweep', 17, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(372, 'Nicobar', 18, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(373, 'North and Middle Andaman', 18, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(374, 'South Andaman', 18, 1, 2, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(375, 'Araria', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(376, 'Arwal', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(377, 'Aurangabad', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(378, 'Banka', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(379, 'Begusarai', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(380, 'Bhagalpur', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(381, 'Bhojpur', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(382, 'Buxar', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(383, 'Darbhanga', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(384, 'East Champaran', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(385, 'Gaya', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(386, 'Gopalganj', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(387, 'Jamui', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(388, 'Jehanabad', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(389, 'Kaimur (Bhabua)', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(390, 'Katihar', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(391, 'Khagaria', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(392, 'Kishanganj', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(393, 'Lakhisarai', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(394, 'Madhepura', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(395, 'Madhubani', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(396, 'Munger', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(397, 'Muzaffarpur', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(398, 'Nalanda', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(399, 'Nawada', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(400, 'Patna', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(401, 'Purnia', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(402, 'Rohtas', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(403, 'Saharsa', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(404, 'Samastipur', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(405, 'Saran', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(406, 'Sheikhpura', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(407, 'Sheohar', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(408, 'Sitamarhi', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(409, 'Siwan', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(410, 'Supaul', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(411, 'Vaishali', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(412, 'West Champaran', 19, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(413, 'Bokaro', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(414, 'Chatra', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(415, 'Deoghar', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(416, 'Dhanbad', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(417, 'Dumka', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(418, 'East Singhbhum', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(419, 'Garhwa', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(420, 'Giridih', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(421, 'Godda', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(422, 'Gumla', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(423, 'Hazaribagh', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(424, 'Jamtara', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(425, 'Khunti', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(426, 'Koderma', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(427, 'Latehar', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(428, 'Lohardaga', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(429, 'Pakur', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(430, 'Palamu', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(431, 'Ramgarh', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(432, 'Ranchi', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(433, 'Sahebganj', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(434, 'Seraikela Kharsawan', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(435, 'Simdega', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(436, 'West Singhbhum', 20, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(437, 'Angul', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(438, 'Balangir', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(439, 'Balasore', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(440, 'Bargarh', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(441, 'Bhadrak', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(442, 'Boudh', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(443, 'Cuttack', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(444, 'Deogarh', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(445, 'Dhenkanal', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(446, 'Gajapati', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(447, 'Ganjam', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(448, 'Jagatsinghpur', 21, 1, 3, 'active', '2025-07-02 07:18:27', '2025-07-02 07:18:27', NULL, NULL),
(449, 'Jajpur', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(450, 'Jharsuguda', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(451, 'Kalahandi', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(452, 'Kandhamal', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(453, 'Kendrapara', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(454, 'Kendujhar (Keonjhar)', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(455, 'Khordha', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(456, 'Koraput', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(457, 'Malkangiri', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(458, 'Mayurbhanj', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(459, 'Nabarangpur', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(460, 'Nayagarh', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(461, 'Nuapada', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(462, 'Puri', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(463, 'Rayagada', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(464, 'Sambalpur', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(465, 'Subarnapur (Sonepur)', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(466, 'Sundargarh', 21, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(467, 'Alipurduar', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(468, 'Bankura', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(469, 'Birbhum', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(470, 'Cooch Behar', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(471, 'Dakshin Dinajpur', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(472, 'Darjeeling', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(473, 'Hooghly', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(474, 'Howrah', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(475, 'Jalpaiguri', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(476, 'Jhargram', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(477, 'Kalimpong', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(478, 'Kolkata', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(479, 'Malda', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(480, 'Murshidabad', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(481, 'Nadia', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(482, 'North 24 Parganas', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(483, 'Paschim Bardhaman', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(484, 'Paschim Medinipur', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(485, 'Purba Bardhaman', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(486, 'Purba Medinipur', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(487, 'Purulia', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(488, 'South 24 Parganas', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(489, 'Uttar Dinajpur', 22, 1, 3, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(490, 'North Goa', 23, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(491, 'South Goa', 23, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(492, 'Ahmedabad', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(493, 'Amreli', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(494, 'Anand', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(495, 'Aravalli', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(496, 'Banaskantha', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(497, 'Bharuch', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(498, 'Bhavnagar', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(499, 'Botad', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(500, 'Chhota Udaipur', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(501, 'Dahod', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(502, 'Dang', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(503, 'Devbhoomi Dwarka', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(504, 'Gandhinagar', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(505, 'Gir Somnath', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(506, 'Jamnagar', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(507, 'Junagadh', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(508, 'Kheda', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(509, 'Kutch', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(510, 'Mahisagar', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(511, 'Mehsana', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(512, 'Morbi', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(513, 'Narmada', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(514, 'Navsari', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(515, 'Panchmahal', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(516, 'Patan', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(517, 'Porbandar', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(518, 'Rajkot', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(519, 'Sabarkantha', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(520, 'Surat', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(521, 'Surendranagar', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(522, 'Tapi', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(523, 'Vadodara', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL);
INSERT INTO `cities` (`id`, `name`, `state_id`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(524, 'Valsad', 24, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(525, 'Ahmednagar', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(526, 'Akola', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(527, 'Amravati', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(528, 'Aurangabad (Chhatrapati Sambhajinagar)', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(529, 'Beed', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(530, 'Bhandara', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(531, 'Buldhana', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(532, 'Chandrapur', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(533, 'Dhule', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(534, 'Gadchiroli', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(535, 'Gondia', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(536, 'Hingoli', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(537, 'Jalgaon', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(538, 'Jalna', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(539, 'Kolhapur', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(540, 'Latur', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(541, 'Mumbai', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-11-05 03:59:07', NULL, NULL),
(542, 'Mumbai Suburban', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(543, 'Nagpur', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(544, 'Nanded', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(545, 'Nandurbar', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(546, 'Nashik', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(547, 'Osmanabad (Dharashiv)', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(548, 'Palghar', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(549, 'Parbhani', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(550, 'Pune', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(551, 'Raigad', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(552, 'Ratnagiri', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(553, 'Sangli', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(554, 'Satara', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(555, 'Sindhudurg', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(556, 'Solapur', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(557, 'Thane', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(558, 'Wardha', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(559, 'Washim', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(560, 'Yavatmal', 25, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(561, 'Dadra and Nagar Haveli', 26, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(562, 'Daman', 26, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(563, 'Diu', 26, 1, 4, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(564, 'Agar Malwa', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(565, 'Alirajpur', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(566, 'Anuppur', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(567, 'Ashoknagar', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(568, 'Balaghat', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(569, 'Barwani', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(570, 'Betul', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(571, 'Bhind', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(572, 'Bhopal', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(573, 'Burhanpur', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(574, 'Chhatarpur', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(575, 'Chhindwara', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(576, 'Damoh', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(577, 'Datia', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(578, 'Dewas', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(579, 'Dhar', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(580, 'Dindori', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(581, 'Guna', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(582, 'Gwalior', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(583, 'Harda', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(584, 'Indore', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(585, 'Jabalpur', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(586, 'Jhabua', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(587, 'Katni', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(588, 'Khandwa', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(589, 'Khargone', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(590, 'Maihar', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(591, 'Mandla', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(592, 'Mandsaur', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(593, 'Mauganj', 27, 1, 5, 'active', '2025-07-02 07:18:28', '2025-07-02 07:18:28', NULL, NULL),
(594, 'Bangalore', 12, 1, NULL, 'active', '2025-11-04 10:18:47', '2025-11-04 10:18:47', NULL, NULL),
(595, 'Mysore', 12, 1, NULL, 'active', '2025-11-11 00:44:48', '2025-11-11 00:44:48', NULL, NULL),
(596, 'Hubli', 12, 1, NULL, 'active', '2025-11-11 00:45:31', '2025-11-11 00:45:31', NULL, NULL),
(597, 'DEVANAHALLI', 12, 1, NULL, 'active', '2025-12-03 00:44:08', '2025-12-03 00:44:08', NULL, NULL),
(598, 'Virar', 25, 1, NULL, 'active', '2025-12-03 00:44:43', '2025-12-03 00:44:43', NULL, NULL),
(599, 'Nathdwara', 4, 1, NULL, 'active', '2025-12-25 01:34:22', '2025-12-25 01:34:22', NULL, NULL),
(600, 'Gurgaon', 3, 1, NULL, 'active', '2025-12-25 01:36:36', '2025-12-25 01:36:36', NULL, NULL),
(601, 'Delhi', 10, 1, NULL, 'active', '2025-12-25 01:38:18', '2025-12-25 01:38:18', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('ADV','CONTRACTOR') NOT NULL,
  `status` enum('ACTIVE','INACTIVE','SUSPENDED') DEFAULT 'ACTIVE',
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `companies`
--

INSERT INTO `companies` (`id`, `name`, `type`, `status`, `contact_email`, `contact_phone`, `address`, `created_at`, `updated_at`) VALUES
(1, 'ADV', 'ADV', 'ACTIVE', 'vishwaaniruddh@gmail.com', '702188883', 'Mumbai', '2025-12-29 20:40:55', '2025-12-29 20:42:49'),
(2, 'Cleared Secured Services', 'CONTRACTOR', 'ACTIVE', 'comfort@comforttechno.com', '9090909090', 'Wadala Truck Terminal', '2025-12-29 20:43:44', '2025-12-29 20:43:44');

-- --------------------------------------------------------

--
-- Table structure for table `company_access_log`
--

CREATE TABLE `company_access_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `target_company_id` int(11) NOT NULL,
  `access_result` enum('GRANTED','DENIED') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_permissions`
--

CREATE TABLE `company_permissions` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `granted_by` int(11) NOT NULL,
  `granted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `revoked_at` timestamp NULL DEFAULT NULL,
  `revoked_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `configuration_audit_log`
--

CREATE TABLE `configuration_audit_log` (
  `id` int(11) NOT NULL,
  `action_type` enum('lock_acquired','lock_released','lock_expired','configured','unbound','ip_created','ip_updated','ip_deleted','bulk_upload') NOT NULL COMMENT 'Type of configuration action',
  `user_id` int(11) NOT NULL COMMENT 'User ID who performed the action',
  `router_serial_number` varchar(100) DEFAULT NULL COMMENT 'Router serial number (if applicable)',
  `ip_master_id` int(11) DEFAULT NULL COMMENT 'Reference to ip_master (if applicable)',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional context in JSON format' CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the action occurred'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configuration audit log for tracking all IP configuration activities';

-- --------------------------------------------------------

--
-- Table structure for table `countries`
--

CREATE TABLE `countries` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `countries`
--

INSERT INTO `countries` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'India', 'active', '2025-11-01 13:17:44', '2025-11-01 13:17:44', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `couriers`
--

CREATE TABLE `couriers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `couriers`
--

INSERT INTO `couriers` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Aramex', 1, '2025-12-29 21:02:13', '2025-12-29 21:02:13', 2326, NULL),
(2, 'Bluedart', 1, '2025-12-29 21:02:21', '2025-12-29 21:02:21', 2326, NULL),
(3, 'Delhivery', 1, '2025-12-29 21:02:26', '2025-12-29 21:02:26', 2326, NULL),
(4, 'DTDC', 1, '2025-12-29 21:02:32', '2025-12-29 21:02:32', 2326, NULL),
(5, 'EcomExpress', 1, '2025-12-29 21:02:41', '2025-12-29 21:02:41', 2326, NULL),
(6, 'FedEx', 1, '2025-12-29 21:02:50', '2025-12-29 21:02:50', 2326, NULL),
(7, 'Gati', 1, '2025-12-29 21:03:01', '2025-12-29 21:03:01', 2326, NULL),
(8, 'India Post', 1, '2025-12-29 21:03:09', '2025-12-29 21:03:09', 2326, NULL),
(9, 'Nandan', 1, '2025-12-29 21:03:17', '2025-12-29 21:03:17', 2326, NULL),
(10, 'Shadowfax', 1, '2025-12-29 21:03:25', '2025-12-29 21:03:25', 2326, NULL),
(11, 'Trackon', 1, '2025-12-29 21:03:32', '2025-12-29 21:03:32', 2326, NULL),
(12, 'XpressBees', 1, '2025-12-29 21:03:39', '2025-12-29 21:03:39', 2326, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
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
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `name`, `email`, `phone`, `address`, `city`, `state`, `country`, `postal_code`, `country_id`, `state_id`, `city_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Euronet', 'euronet@euronet.com', '9090901010', 'wadala truck terminal', 'Mumbai', 'Maharashtra', 'India', '400057', NULL, NULL, NULL, 1, '2025-12-29 21:00:10', '2025-12-29 21:40:31', 2326, 2326),
(2, 'Hitachi', 'hitachi@hitachi.com', '8787878787', 'Thane', 'Thane', 'Maharashtra', 'India', '400612', NULL, NULL, NULL, 1, '2025-12-29 21:45:18', '2025-12-29 21:45:18', 2326, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `delegation_history`
--

CREATE TABLE `delegation_history` (
  `id` int(11) NOT NULL,
  `delegation_id` int(11) NOT NULL,
  `action` enum('created','accepted','rejected','reassigned') NOT NULL,
  `performed_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `discrepancies`
--

CREATE TABLE `discrepancies` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `pending_receive_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `expected_quantity` int(11) NOT NULL,
  `received_quantity` int(11) NOT NULL,
  `discrepancy_type` enum('shortage','damage','wrong_item','excess') NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('open','resolved','written_off') DEFAULT 'open',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispatches`
--

CREATE TABLE `dispatches` (
  `id` int(11) NOT NULL,
  `dispatch_number` varchar(50) NOT NULL,
  `sender_type` enum('warehouse','company','user') DEFAULT 'warehouse',
  `sender_id` int(11) DEFAULT NULL,
  `from_company_id` int(11) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_company_id` int(11) DEFAULT NULL,
  `to_user_id` int(11) DEFAULT NULL,
  `to_warehouse_id` int(11) DEFAULT NULL,
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
  `acknowledgment_proof` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`acknowledgment_proof`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispatch_chain`
--

CREATE TABLE `dispatch_chain` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL COMMENT 'For serializable items',
  `product_id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `sequence_number` int(11) NOT NULL COMMENT 'Order in the chain for this item',
  `from_entity_type` enum('warehouse','company','user') NOT NULL,
  `from_entity_id` int(11) NOT NULL,
  `to_entity_type` enum('warehouse','company','user') NOT NULL,
  `to_entity_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `dispatch_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `acceptance_date` timestamp NULL DEFAULT NULL,
  `status` enum('dispatched','accepted','rejected') NOT NULL DEFAULT 'dispatched',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dispatch_items`
--

CREATE TABLE `dispatch_items` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL COMMENT 'For serializable items, references specific asset',
  `quantity` int(11) DEFAULT 1 COMMENT 'For non-serializable items, quantity dispatched',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `engineer_assignments`
--

CREATE TABLE `engineer_assignments` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `delegation_id` int(11) NOT NULL COMMENT 'Reference to accepted delegation',
  `engineer_id` int(11) NOT NULL COMMENT 'User ID of engineer',
  `assigned_by` int(11) NOT NULL COMMENT 'User ID who assigned',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('assigned','in_progress','completed') DEFAULT 'assigned',
  `feasibility_status` enum('pending_eta','eta_submitted','ada_submitted','feasibility_completed','pending_contractor_review','contractor_approved','contractor_rejected','adv_approved','adv_rejected') DEFAULT 'pending_eta' COMMENT 'Feasibility workflow status including approval workflow',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feasibility_ada`
--

CREATE TABLE `feasibility_ada` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `ada_datetime` datetime NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feasibility_checks`
--

CREATE TABLE `feasibility_checks` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `no_of_atm` int(11) DEFAULT 0,
  `atm_id_1` varchar(100) DEFAULT NULL,
  `atm_id_2` varchar(100) DEFAULT NULL,
  `atm_id_3` varchar(100) DEFAULT NULL,
  `atm_1_status` varchar(50) DEFAULT NULL,
  `atm_2_status` varchar(50) DEFAULT NULL,
  `atm_3_status` varchar(50) DEFAULT NULL,
  `operator` varchar(100) DEFAULT NULL,
  `signal_status` varchar(50) DEFAULT NULL,
  `operator_2` varchar(100) DEFAULT NULL,
  `signal_status_2` varchar(50) DEFAULT NULL,
  `backroom_network_remark` text DEFAULT NULL,
  `ups_available` varchar(10) DEFAULT NULL,
  `no_of_ups` int(11) DEFAULT NULL,
  `ups_battery_backup` varchar(50) DEFAULT NULL,
  `ups_working_1` varchar(50) DEFAULT NULL,
  `ups_working_2` varchar(50) DEFAULT NULL,
  `ups_working_3` varchar(50) DEFAULT NULL,
  `power_socket_availability` varchar(50) DEFAULT NULL,
  `power_socket_availability_ups` varchar(50) DEFAULT NULL,
  `earthing` varchar(50) DEFAULT NULL,
  `earthing_voltage` varchar(50) DEFAULT NULL,
  `power_fluctuation_en` varchar(50) DEFAULT NULL,
  `power_fluctuation_pe` varchar(50) DEFAULT NULL,
  `power_fluctuation_pn` varchar(50) DEFAULT NULL,
  `frequent_power_cut` varchar(10) DEFAULT NULL,
  `frequent_power_cut_from` time DEFAULT NULL,
  `frequent_power_cut_to` time DEFAULT NULL,
  `frequent_power_cut_remark` text DEFAULT NULL,
  `em_lock_available` varchar(10) DEFAULT NULL,
  `em_lock_password` varchar(100) DEFAULT NULL,
  `password_received` varchar(10) DEFAULT NULL,
  `backroom_key_name` varchar(100) DEFAULT NULL,
  `backroom_key_number` varchar(50) DEFAULT NULL,
  `backroom_key_status` varchar(50) DEFAULT NULL,
  `antenna_routing_detail` text DEFAULT NULL,
  `router_antenna_position` varchar(100) DEFAULT NULL,
  `router_position` varchar(100) DEFAULT NULL,
  `nearest_shop_name` varchar(200) DEFAULT NULL,
  `nearest_shop_number` varchar(50) DEFAULT NULL,
  `nearest_shop_distance` varchar(50) DEFAULT NULL,
  `backroom_disturbing_material` varchar(10) DEFAULT NULL,
  `backroom_disturbing_material_remark` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `backroom_network_snap` varchar(500) DEFAULT NULL,
  `router_antenna_snap` varchar(500) DEFAULT NULL,
  `antenna_routing_snap` varchar(500) DEFAULT NULL,
  `ups_available_snap` varchar(500) DEFAULT NULL,
  `no_of_ups_snap` varchar(500) DEFAULT NULL,
  `ups_working_snap` varchar(500) DEFAULT NULL,
  `power_socket_availability_snap` varchar(500) DEFAULT NULL,
  `earthing_snap` varchar(500) DEFAULT NULL,
  `power_fluctuation_snap` varchar(500) DEFAULT NULL,
  `remarks_snap` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `approval_status` enum('pending_contractor_review','contractor_approved','contractor_rejected','adv_approved','adv_rejected') DEFAULT 'pending_contractor_review',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feasibility_eta`
--

CREATE TABLE `feasibility_eta` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `eta_datetime` datetime NOT NULL,
  `submitted_by` int(11) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_current` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feasibility_reviews`
--

CREATE TABLE `feasibility_reviews` (
  `id` int(11) NOT NULL,
  `feasibility_id` int(11) NOT NULL COMMENT 'Reference to feasibility_checks',
  `reviewer_id` int(11) NOT NULL COMMENT 'User ID of the reviewer',
  `reviewer_role` enum('contractor_admin','contractor_manager','adv') NOT NULL COMMENT 'Role of the reviewer',
  `review_type` enum('approval','rejection') NOT NULL COMMENT 'Type of review action',
  `rejection_type` enum('overall','section_specific') DEFAULT NULL COMMENT 'Type of rejection (null if approval)',
  `rejected_sections` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array of section names that were rejected' CHECK (json_valid(`rejected_sections`)),
  `reason` text DEFAULT NULL COMMENT 'Required for rejections, min 10 characters',
  `comments` text DEFAULT NULL COMMENT 'Optional comments for approvals',
  `reviewed_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the review was submitted',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_audit_log`
--

CREATE TABLE `inventory_audit_log` (
  `id` int(11) NOT NULL,
  `action_type` varchar(50) NOT NULL COMMENT 'stock_entry, dispatch, transfer, status_change, repair, etc.',
  `entity_type` varchar(50) NOT NULL COMMENT 'asset, stock, dispatch, transfer, repair',
  `entity_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `from_location_type` varchar(50) DEFAULT NULL COMMENT 'warehouse, company, user',
  `from_location_id` int(11) DEFAULT NULL,
  `to_location_type` varchar(50) DEFAULT NULL COMMENT 'warehouse, company, user',
  `to_location_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Previous state before action' CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'New state after action' CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_counters`
--

CREATE TABLE `inventory_counters` (
  `id` int(11) NOT NULL,
  `entity_type` enum('warehouse','company','user') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `pending_out` int(11) DEFAULT 0 COMMENT 'Quantity in pending outgoing dispatches',
  `pending_in` int(11) DEFAULT 0 COMMENT 'Quantity in pending incoming receives',
  `last_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `inventory_notifications`
--

CREATE TABLE `inventory_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type` enum('pending_receive','accepted','rejected','overdue','discrepancy') NOT NULL,
  `dispatch_id` int(11) DEFAULT NULL,
  `pending_receive_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ip_locks`
--

CREATE TABLE `ip_locks` (
  `id` int(11) NOT NULL,
  `ip_master_id` int(11) NOT NULL COMMENT 'Reference to ip_master table',
  `router_serial_number` varchar(100) NOT NULL COMMENT 'Serial number of router being configured',
  `locked_by` int(11) NOT NULL COMMENT 'User ID who acquired the lock',
  `locked_at` datetime DEFAULT current_timestamp() COMMENT 'When the lock was acquired',
  `expires_at` datetime NOT NULL COMMENT 'When the lock expires (locked_at + 20 minutes)',
  `status` enum('active','released','expired') DEFAULT 'active' COMMENT 'Current lock status',
  `released_at` datetime DEFAULT NULL COMMENT 'When the lock was released (if applicable)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP Locks table for temporary locking during 20-minute configuration process';

-- --------------------------------------------------------

--
-- Table structure for table `ip_master`
--

CREATE TABLE `ip_master` (
  `id` int(11) NOT NULL,
  `network_ip` varchar(15) NOT NULL COMMENT 'Network IP address',
  `router_ip` varchar(15) NOT NULL COMMENT 'Router IP address',
  `site_ip` varchar(15) NOT NULL COMMENT 'Site IP address',
  `subnet_mask` varchar(15) NOT NULL COMMENT 'Subnet mask',
  `status` enum('available','locked','configured') DEFAULT 'available' COMMENT 'Current status of IP combination',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created this record',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP Master table storing unique IP address combinations for router configuration';

--
-- Dumping data for table `ip_master`
--

INSERT INTO `ip_master` (`id`, `network_ip`, `router_ip`, `site_ip`, `subnet_mask`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(1736, '53.227.113.59', '42.57.205.207', '130.195.57.91', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-31 15:02:35'),
(1737, '107.204.184.31', '242.65.1.70', '247.111.18.13', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 11:42:07'),
(1738, '232.73.47.21', '215.247.214.143', '50.138.72.81', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-31 15:02:35'),
(1739, '186.157.68.180', '198.74.247.81', '187.51.220.35', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1740, '197.221.192.231', '238.59.98.26', '44.208.222.42', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1741, '72.199.94.144', '85.122.136.195', '137.96.76.226', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1742, '204.196.34.194', '100.139.158.242', '211.60.42.117', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1743, '173.32.153.167', '76.50.67.13', '47.214.212.154', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1744, '161.242.254.193', '20.186.192.13', '171.124.3.21', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1745, '128.22.179.171', '204.134.119.184', '92.177.240.44', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1746, '181.192.134.239', '150.239.224.30', '237.194.178.64', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1747, '224.41.204.127', '10.142.24.63', '72.188.16.100', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1748, '97.46.56.83', '91.100.126.200', '130.77.93.173', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1749, '16.208.145.218', '119.30.6.73', '162.59.70.93', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1750, '181.165.114.130', '124.235.23.122', '39.67.18.118', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1751, '202.204.26.178', '52.155.226.47', '101.240.8.181', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1752, '109.148.99.131', '142.105.221.188', '203.28.134.170', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1753, '167.28.187.79', '37.27.139.126', '105.59.49.14', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1754, '241.74.3.212', '201.108.62.164', '136.26.3.11', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1755, '131.140.105.37', '233.147.184.191', '217.4.102.51', '255.255.255.0', 'available', 2326, '2025-12-30 09:05:50', '2025-12-30 09:05:50'),
(1876, '159.181.151.0', '52.184.62.211', '165.226.141.99', '255.255.255.0', 'available', 2326, '2025-12-30 09:07:49', '2025-12-30 09:07:49');

-- --------------------------------------------------------

--
-- Table structure for table `ip_restrictions`
--

CREATE TABLE `ip_restrictions` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `restriction_type` enum('WHITELIST','BLACKLIST') NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lhos`
--

CREATE TABLE `lhos` (
  `id` int(11) NOT NULL,
  `lho_name` varchar(255) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lhos`
--

INSERT INTO `lhos` (`id`, `lho_name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Mumbai', 'active', '2025-12-29 21:31:29', '2025-12-29 21:31:29', 2326, NULL),
(2, 'Pune', 'active', '2025-12-29 21:38:43', '2025-12-29 21:38:43', 2326, NULL),
(3, 'Delhi', 'active', '2025-12-29 21:38:59', '2025-12-29 21:38:59', 2326, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `failure_reason` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(11) NOT NULL,
  `migration` varchar(255) NOT NULL,
  `executed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `executed_at`) VALUES
(1, '2024_12_27_100000_create_core_tables', '2025-12-27 09:45:18'),
(2, '2024_12_27_200000_create_company_access_log', '2025-12-27 11:18:43'),
(3, '2024_12_28_600000_create_couriers_table', '2025-12-28 11:12:34'),
(4, '2024_12_28_100000_create_api_access_log', '2025-12-28 21:23:58'),
(5, '2024_12_28_200000_create_security_tables', '2025-12-28 21:23:58'),
(6, '2024_12_28_300000_add_system_manage_permission', '2025-12-28 21:24:44'),
(7, '2024_12_28_400000_create_master_module_tables', '2025-12-28 21:24:44'),
(8, '2024_12_28_500000_add_master_module_permissions', '2025-12-28 21:24:44'),
(9, '2024_12_28_700000_add_courier_permissions', '2025-12-28 21:24:44'),
(10, '2024_12_28_800000_create_sites_table', '2025-12-28 21:24:44'),
(11, '2024_12_28_800001_create_site_delegations_table', '2025-12-28 21:24:44'),
(12, '2024_12_28_800002_create_engineer_assignments_table', '2025-12-28 21:24:44'),
(13, '2024_12_28_800003_create_delegation_history_table', '2025-12-28 21:24:44'),
(14, '2024_12_28_900000_add_site_management_permissions', '2025-12-28 21:24:45'),
(15, '2024_12_29_100000_create_warehouses_table', '2025-12-28 21:24:45'),
(16, '2024_12_29_200000_create_products_tables', '2025-12-28 21:24:45'),
(17, '2024_12_29_300000_create_stock_assets_tables', '2025-12-28 21:24:45'),
(18, '2024_12_29_400000_create_dispatches_tables', '2025-12-28 21:24:45'),
(19, '2024_12_29_500000_create_transfers_tables', '2025-12-28 21:24:45'),
(20, '2024_12_29_600000_create_repairs_table', '2025-12-28 21:24:45'),
(21, '2024_12_29_700000_create_alerts_audit_tables', '2025-12-28 21:24:45'),
(22, '2024_12_29_800000_add_inventory_permissions', '2025-12-28 21:24:45');

-- --------------------------------------------------------

--
-- Table structure for table `password_history`
--

CREATE TABLE `password_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_receives`
--

CREATE TABLE `pending_receives` (
  `id` int(11) NOT NULL,
  `dispatch_id` int(11) NOT NULL,
  `recipient_type` enum('warehouse','company','user') NOT NULL,
  `recipient_id` int(11) NOT NULL,
  `status` enum('pending','accepted','rejected','partial') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `accepted_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pending_receive_items`
--

CREATE TABLE `pending_receive_items` (
  `id` int(11) NOT NULL,
  `pending_receive_id` int(11) NOT NULL,
  `dispatch_item_id` int(11) NOT NULL,
  `expected_quantity` int(11) NOT NULL,
  `received_quantity` int(11) DEFAULT 0,
  `status` enum('pending','accepted','rejected','partial') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `is_adv_only` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `module`, `action`, `description`, `is_adv_only`, `created_at`, `updated_at`) VALUES
(1, 'users.create', 'users', 'create', 'Create new users', 0, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(2, 'users.read', 'users', 'read', 'View user information', 0, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(3, 'users.update', 'users', 'update', 'Update user information', 0, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(4, 'users.delete', 'users', 'delete', 'Delete users', 0, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(5, 'users.manage', 'users', 'manage', 'Full user management', 0, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(6, 'companies.create', 'companies', 'create', 'Create new companies', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(7, 'companies.read', 'companies', 'read', 'View company information', 0, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(8, 'companies.update', 'companies', 'update', 'Update company information', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(9, 'companies.delete', 'companies', 'delete', 'Delete companies', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(10, 'companies.manage', 'companies', 'manage', 'Full company management', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(11, 'roles.create', 'roles', 'create', 'Create new roles', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(12, 'roles.read', 'roles', 'read', 'View role information', 0, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(13, 'roles.update', 'roles', 'update', 'Update role information', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(14, 'roles.delete', 'roles', 'delete', 'Delete roles', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(15, 'roles.manage', 'roles', 'manage', 'Full role management', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(16, 'permissions.delegate', 'permissions', 'delegate', 'Delegate permissions to contractors', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(17, 'permissions.revoke', 'permissions', 'revoke', 'Revoke permissions from contractors', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(18, 'permissions.read', 'permissions', 'read', 'View permission information', 0, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(19, 'permissions.manage', 'permissions', 'manage', 'Full permission management', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(20, 'system.admin', 'system', 'admin', 'System administration access', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(21, 'system.audit', 'system', 'audit', 'View audit logs', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(22, 'system.backup', 'system', 'backup', 'System backup operations', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(23, 'master_data.read', 'master_data', 'read', 'View master data', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(24, 'master_data.manage', 'master_data', 'manage', 'Manage master data', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(25, 'admin.dashboard', 'admin', 'dashboard', 'Access admin dashboard', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(26, 'admin.reports', 'admin', 'reports', 'Generate admin reports', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(1417, 'masters.banks.view', 'masters.banks', 'view', 'View bank records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1418, 'masters.banks.create', 'masters.banks', 'create', 'Create bank records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1419, 'masters.banks.edit', 'masters.banks', 'edit', 'Edit bank records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1420, 'masters.banks.delete', 'masters.banks', 'delete', 'Delete bank records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1421, 'masters.customers.view', 'masters.customers', 'view', 'View customer records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1422, 'masters.customers.create', 'masters.customers', 'create', 'Create customer records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1423, 'masters.customers.edit', 'masters.customers', 'edit', 'Edit customer records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1424, 'masters.customers.delete', 'masters.customers', 'delete', 'Delete customer records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1425, 'masters.locations.view', 'masters.locations', 'view', 'View location records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1426, 'masters.locations.create', 'masters.locations', 'create', 'Create location records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1427, 'masters.locations.edit', 'masters.locations', 'edit', 'Edit location records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1428, 'masters.locations.delete', 'masters.locations', 'delete', 'Delete location records', 1, '2025-12-28 07:35:58', '2025-12-28 07:35:58'),
(1429, 'masters.couriers.view', 'masters.couriers', 'view', 'View courier records', 1, '2025-12-28 11:12:34', '2025-12-28 11:12:34'),
(1430, 'masters.couriers.create', 'masters.couriers', 'create', 'Create courier records', 1, '2025-12-28 11:12:34', '2025-12-28 11:12:34'),
(1431, 'masters.couriers.edit', 'masters.couriers', 'edit', 'Edit courier records', 1, '2025-12-28 11:12:34', '2025-12-28 11:12:34'),
(1432, 'masters.couriers.delete', 'masters.couriers', 'delete', 'Delete courier records', 1, '2025-12-28 11:12:34', '2025-12-28 11:12:34'),
(1847, 'sites.view', 'sites', 'view', 'View site records', 1, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1848, 'sites.create', 'sites', 'create', 'Create new site records', 1, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1849, 'sites.edit', 'sites', 'edit', 'Edit existing site records', 1, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1850, 'sites.delete', 'sites', 'delete', 'Delete site records', 1, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1851, 'sites.delegate', 'sites', 'delegate', 'Delegate sites to contractors', 1, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1852, 'sites.bulk_upload', 'sites', 'bulk_upload', 'Bulk upload sites via Excel', 1, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1853, 'delegations.view', 'delegations', 'view', 'View delegation tracking dashboard', 1, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1854, 'delegations.export', 'delegations', 'export', 'Export delegation data to Excel', 1, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1855, 'contractor.delegations.view', 'contractor', 'delegations_view', 'View sites delegated to contractor', 0, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1856, 'contractor.delegations.respond', 'contractor', 'delegations_respond', 'Accept or reject site delegations', 0, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1857, 'contractor.assignments.manage', 'contractor', 'assignments_manage', 'Assign sites to engineers', 0, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1858, 'contractor.assignments.bulk', 'contractor', 'assignments_bulk', 'Bulk assign sites to engineers via Excel', 0, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(1859, 'engineer.sites.view', 'engineer', 'sites_view', 'View sites assigned to engineer', 0, '2025-12-28 16:51:45', '2025-12-28 16:51:45'),
(2181, 'system.manage', 'system', 'manage', 'Access system administration tools including health monitoring, backups, and maintenance', 1, '2025-12-28 21:24:44', '2025-12-28 21:24:44'),
(2194, 'inventory.warehouses.create', 'inventory', 'warehouses.create', 'Create warehouses', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2195, 'inventory.warehouses.read', 'inventory', 'warehouses.read', 'View warehouses', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2196, 'inventory.warehouses.update', 'inventory', 'warehouses.update', 'Update warehouses', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2197, 'inventory.warehouses.delete', 'inventory', 'warehouses.delete', 'Delete warehouses', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2198, 'inventory.warehouses.manage', 'inventory', 'warehouses.manage', 'Full warehouse management', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2199, 'inventory.products.create', 'inventory', 'products.create', 'Create products', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2200, 'inventory.products.read', 'inventory', 'products.read', 'View products', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2201, 'inventory.products.update', 'inventory', 'products.update', 'Update products', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2202, 'inventory.products.delete', 'inventory', 'products.delete', 'Delete products', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2203, 'inventory.products.manage', 'inventory', 'products.manage', 'Full product management', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2204, 'inventory.stock.create', 'inventory', 'stock.create', 'Add stock entries', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2205, 'inventory.stock.read', 'inventory', 'stock.read', 'View stock levels', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2206, 'inventory.stock.update', 'inventory', 'stock.update', 'Update stock entries', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2207, 'inventory.stock.bulk_upload', 'inventory', 'stock.bulk_upload', 'Bulk stock upload', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2208, 'inventory.stock.manage', 'inventory', 'stock.manage', 'Full stock management', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2209, 'inventory.dispatch.create', 'inventory', 'dispatch.create', 'Create dispatches', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2210, 'inventory.dispatch.read', 'inventory', 'dispatch.read', 'View dispatches', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2211, 'inventory.dispatch.update', 'inventory', 'dispatch.update', 'Update dispatches', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2212, 'inventory.dispatch.acknowledge', 'inventory', 'dispatch.acknowledge', 'Acknowledge dispatch receipt', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2213, 'inventory.dispatch.manage', 'inventory', 'dispatch.manage', 'Full dispatch management', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2214, 'inventory.transfer.create', 'inventory', 'transfer.create', 'Create transfers', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2215, 'inventory.transfer.read', 'inventory', 'transfer.read', 'View transfers', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2216, 'inventory.transfer.update', 'inventory', 'transfer.update', 'Update transfers', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2217, 'inventory.transfer.manage', 'inventory', 'transfer.manage', 'Full transfer management', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2218, 'inventory.assets.read', 'inventory', 'assets.read', 'View assets', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2219, 'inventory.assets.update_status', 'inventory', 'assets.update_status', 'Update asset status', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2220, 'inventory.assets.update_status_full', 'inventory', 'assets.update_status_full', 'Update asset status (all statuses)', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2221, 'inventory.assets.manage', 'inventory', 'assets.manage', 'Full asset management', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2222, 'inventory.repairs.create', 'inventory', 'repairs.create', 'Create repair requests', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2223, 'inventory.repairs.read', 'inventory', 'repairs.read', 'View repairs', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2224, 'inventory.repairs.update', 'inventory', 'repairs.update', 'Update repairs', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2225, 'inventory.repairs.complete', 'inventory', 'repairs.complete', 'Complete repairs', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2226, 'inventory.repairs.manage', 'inventory', 'repairs.manage', 'Full repair management', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2227, 'inventory.dashboard.adv', 'inventory', 'dashboard.adv', 'Access ADV inventory dashboard', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2228, 'inventory.dashboard.contractor', 'inventory', 'dashboard.contractor', 'Access contractor inventory dashboard', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2229, 'inventory.dashboard.engineer', 'inventory', 'dashboard.engineer', 'Access engineer inventory dashboard', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2230, 'inventory.reports.read', 'inventory', 'reports.read', 'View inventory reports', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2231, 'inventory.reports.export', 'inventory', 'reports.export', 'Export inventory data', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2232, 'inventory.audit.read', 'inventory', 'audit.read', 'View inventory audit logs', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2233, 'inventory.alerts.read', 'inventory', 'alerts.read', 'View inventory alerts', 0, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2234, 'inventory.alerts.manage', 'inventory', 'alerts.manage', 'Manage inventory alerts and thresholds', 1, '2025-12-28 21:24:45', '2025-12-28 21:24:45'),
(2235, 'masters.product_categories.view', 'masters', 'product_categories.view', 'View product categories', 1, '2025-12-29 19:40:50', '2025-12-29 19:40:50'),
(2236, 'masters.product_categories.create', 'masters', 'product_categories.create', 'Create product categories', 1, '2025-12-29 19:40:50', '2025-12-29 19:40:50'),
(2237, 'masters.product_categories.edit', 'masters', 'product_categories.edit', 'Edit product categories', 1, '2025-12-29 19:40:50', '2025-12-29 19:40:50'),
(2238, 'masters.product_categories.delete', 'masters', 'product_categories.delete', 'Delete product categories', 1, '2025-12-29 19:40:50', '2025-12-29 19:40:50'),
(2239, 'masters.lhos.view', 'masters', 'lhos.view', 'View LHO records', 1, '2025-12-29 21:17:31', '2025-12-29 21:17:31'),
(2240, 'masters.lhos.create', 'masters', 'lhos.create', 'Create LHO records', 1, '2025-12-29 21:17:31', '2025-12-29 21:17:31'),
(2241, 'masters.lhos.edit', 'masters', 'lhos.edit', 'Edit LHO records', 1, '2025-12-29 21:17:31', '2025-12-29 21:17:31'),
(2242, 'masters.lhos.delete', 'masters', 'lhos.delete', 'Delete LHO records', 1, '2025-12-29 21:17:31', '2025-12-29 21:17:31'),
(2243, 'engineer.eta.submit', 'engineer', 'eta_submit', 'Submit ETA for assigned sites', 0, '2025-12-31 09:29:02', '2025-12-31 09:29:02'),
(2244, 'engineer.ada.submit', 'engineer', 'ada_submit', 'Submit ADA with geolocation for assigned sites', 0, '2025-12-31 09:29:02', '2025-12-31 09:29:02'),
(2245, 'engineer.feasibility.submit', 'engineer', 'feasibility_submit', 'Submit feasibility check form for assigned sites', 0, '2025-12-31 09:29:02', '2025-12-31 09:29:02'),
(2246, 'feasibility.review.contractor', 'feasibility', 'review_contractor', 'Review and approve/reject feasibility checks (Contractor Admin/Manager)', 0, '2025-12-31 09:29:02', '2025-12-31 09:29:02'),
(2247, 'feasibility.tracking.view', 'feasibility', 'tracking_view', 'View feasibility tracking dashboard', 1, '2025-12-31 09:29:02', '2025-12-31 09:29:02'),
(2248, 'feasibility.tracking.export', 'feasibility', 'tracking_export', 'Export feasibility data to Excel', 1, '2025-12-31 09:29:02', '2025-12-31 09:29:02'),
(2249, 'feasibility.review.adv', 'feasibility', 'review_adv', 'ADV final approval for feasibility checks', 1, '2025-12-31 09:29:02', '2025-12-31 09:29:02');

-- --------------------------------------------------------

--
-- Table structure for table `permission_audit_log`
--

CREATE TABLE `permission_audit_log` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `permission_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `performed_by` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `permission_audit_log`
--

INSERT INTO `permission_audit_log` (`id`, `company_id`, `permission_id`, `action`, `details`, `performed_by`, `timestamp`) VALUES
(1, 1, 5, '', NULL, 2326, '2025-12-29 20:41:43');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `unit_of_measure` varchar(50) NOT NULL,
  `inventory_type` enum('INTERNAL','SITE') NOT NULL,
  `is_serializable` tinyint(1) DEFAULT 0,
  `is_repairable` tinyint(1) DEFAULT 0,
  `low_stock_threshold` int(11) DEFAULT 0,
  `status` enum('active','inactive') DEFAULT 'active',
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `repairs`
--

CREATE TABLE `repairs` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `repair_vendor` varchar(150) DEFAULT NULL,
  `estimated_cost` decimal(10,2) DEFAULT NULL,
  `actual_cost` decimal(10,2) DEFAULT NULL,
  `send_date` date NOT NULL,
  `expected_return_date` date DEFAULT NULL,
  `actual_return_date` date DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending',
  `repair_notes` text DEFAULT NULL COMMENT 'Notes about the repair work',
  `diagnosis` text DEFAULT NULL COMMENT 'Initial diagnosis of the issue',
  `resolution` text DEFAULT NULL COMMENT 'Description of repair work done',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `level` int(11) NOT NULL DEFAULT 1,
  `company_type` enum('ADV','CONTRACTOR','BOTH') NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `level`, `company_type`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 10, 'ADV', 'Full system administrator with all permissions', 1, '2025-12-27 09:45:18', '2025-12-29 19:42:47'),
(2, 'ADV Admin', 8, 'ADV', 'ADV administrator with delegation capabilities', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(3, 'ADV Manager', 6, 'ADV', 'ADV manager with limited administrative access', 1, '2025-12-27 09:45:18', '2025-12-30 14:54:35'),
(4, 'ADV User', 4, 'ADV', 'Standard ADV user', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(5, 'Contractor Admin', 7, 'CONTRACTOR', 'Contractor administrator', 1, '2025-12-27 09:45:18', '2025-12-31 13:24:21'),
(6, 'Contractor Manager', 5, 'CONTRACTOR', 'Contractor manager', 1, '2025-12-27 09:45:18', '2025-12-29 22:09:49'),
(7, 'Contractor User', 3, 'CONTRACTOR', 'Standard contractor user', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18'),
(8, 'Engineer', 2, 'CONTRACTOR', 'Field engineer', 1, '2025-12-27 09:45:18', '2025-12-29 22:44:03'),
(9, 'Viewer', 1, 'BOTH', 'Read-only access', 1, '2025-12-27 09:45:18', '2025-12-27 09:45:18');

-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role_id`, `permission_id`, `created_at`) VALUES
(1, 1, 1, '2025-12-29 21:27:51'),
(2, 1, 2, '2025-12-29 21:27:51'),
(3, 1, 3, '2025-12-29 21:27:51'),
(4, 1, 4, '2025-12-29 21:27:51'),
(5, 1, 5, '2025-12-29 21:27:51'),
(6, 1, 7, '2025-12-29 21:27:51'),
(7, 1, 12, '2025-12-29 21:27:51'),
(8, 1, 18, '2025-12-29 21:27:51'),
(9, 1, 1855, '2025-12-29 21:27:51'),
(10, 1, 1856, '2025-12-29 21:27:51'),
(11, 1, 1857, '2025-12-29 21:27:51'),
(12, 1, 1858, '2025-12-29 21:27:51'),
(13, 1, 1859, '2025-12-29 21:27:51'),
(14, 1, 2195, '2025-12-29 21:27:51'),
(15, 1, 2200, '2025-12-29 21:27:51'),
(16, 1, 2205, '2025-12-29 21:27:51'),
(17, 1, 2209, '2025-12-29 21:27:51'),
(18, 1, 2210, '2025-12-29 21:27:51'),
(19, 1, 2211, '2025-12-29 21:27:51'),
(20, 1, 2212, '2025-12-29 21:27:51'),
(21, 1, 2215, '2025-12-29 21:27:51'),
(22, 1, 2218, '2025-12-29 21:27:51'),
(23, 1, 2219, '2025-12-29 21:27:51'),
(24, 1, 2222, '2025-12-29 21:27:51'),
(25, 1, 2223, '2025-12-29 21:27:51'),
(26, 1, 2228, '2025-12-29 21:27:51'),
(27, 1, 2229, '2025-12-29 21:27:51'),
(28, 1, 2230, '2025-12-29 21:27:51'),
(29, 1, 2231, '2025-12-29 21:27:51'),
(30, 1, 2233, '2025-12-29 21:27:51'),
(31, 1, 6, '2025-12-29 21:27:51'),
(32, 1, 8, '2025-12-29 21:27:51'),
(33, 1, 9, '2025-12-29 21:27:51'),
(34, 1, 10, '2025-12-29 21:27:51'),
(35, 1, 11, '2025-12-29 21:27:51'),
(36, 1, 13, '2025-12-29 21:27:51'),
(37, 1, 14, '2025-12-29 21:27:51'),
(38, 1, 15, '2025-12-29 21:27:51'),
(39, 1, 16, '2025-12-29 21:27:51'),
(40, 1, 17, '2025-12-29 21:27:51'),
(41, 1, 19, '2025-12-29 21:27:51'),
(42, 1, 20, '2025-12-29 21:27:51'),
(43, 1, 21, '2025-12-29 21:27:51'),
(44, 1, 22, '2025-12-29 21:27:51'),
(45, 1, 23, '2025-12-29 21:27:51'),
(46, 1, 24, '2025-12-29 21:27:51'),
(47, 1, 25, '2025-12-29 21:27:51'),
(48, 1, 26, '2025-12-29 21:27:51'),
(49, 1, 1417, '2025-12-29 21:27:51'),
(50, 1, 1418, '2025-12-29 21:27:51'),
(51, 1, 1419, '2025-12-29 21:27:51'),
(52, 1, 1420, '2025-12-29 21:27:51'),
(53, 1, 1421, '2025-12-29 21:27:51'),
(54, 1, 1422, '2025-12-29 21:27:51'),
(55, 1, 1423, '2025-12-29 21:27:51'),
(56, 1, 1424, '2025-12-29 21:27:51'),
(57, 1, 1425, '2025-12-29 21:27:51'),
(58, 1, 1426, '2025-12-29 21:27:51'),
(59, 1, 1427, '2025-12-29 21:27:51'),
(60, 1, 1428, '2025-12-29 21:27:51'),
(61, 1, 1429, '2025-12-29 21:27:51'),
(62, 1, 1430, '2025-12-29 21:27:51'),
(63, 1, 1431, '2025-12-29 21:27:51'),
(64, 1, 1432, '2025-12-29 21:27:51'),
(65, 1, 1847, '2025-12-29 21:27:51'),
(66, 1, 1848, '2025-12-29 21:27:51'),
(67, 1, 1849, '2025-12-29 21:27:51'),
(68, 1, 1850, '2025-12-29 21:27:51'),
(69, 1, 1851, '2025-12-29 21:27:51'),
(70, 1, 1852, '2025-12-29 21:27:51'),
(71, 1, 1853, '2025-12-29 21:27:51'),
(72, 1, 1854, '2025-12-29 21:27:51'),
(73, 1, 2181, '2025-12-29 21:27:51'),
(74, 1, 2194, '2025-12-29 21:27:51'),
(75, 1, 2196, '2025-12-29 21:27:51'),
(76, 1, 2197, '2025-12-29 21:27:51'),
(77, 1, 2198, '2025-12-29 21:27:51'),
(78, 1, 2199, '2025-12-29 21:27:51'),
(79, 1, 2201, '2025-12-29 21:27:51'),
(80, 1, 2202, '2025-12-29 21:27:51'),
(81, 1, 2203, '2025-12-29 21:27:51'),
(82, 1, 2204, '2025-12-29 21:27:51'),
(83, 1, 2206, '2025-12-29 21:27:51'),
(84, 1, 2207, '2025-12-29 21:27:51'),
(85, 1, 2208, '2025-12-29 21:27:51'),
(86, 1, 2213, '2025-12-29 21:27:51'),
(87, 1, 2214, '2025-12-29 21:27:51'),
(88, 1, 2216, '2025-12-29 21:27:51'),
(89, 1, 2217, '2025-12-29 21:27:51'),
(90, 1, 2220, '2025-12-29 21:27:51'),
(91, 1, 2221, '2025-12-29 21:27:51'),
(92, 1, 2224, '2025-12-29 21:27:51'),
(93, 1, 2225, '2025-12-29 21:27:51'),
(94, 1, 2226, '2025-12-29 21:27:51'),
(95, 1, 2227, '2025-12-29 21:27:51'),
(96, 1, 2232, '2025-12-29 21:27:51'),
(97, 1, 2234, '2025-12-29 21:27:51'),
(98, 1, 2235, '2025-12-29 21:27:51'),
(99, 1, 2236, '2025-12-29 21:27:51'),
(100, 1, 2237, '2025-12-29 21:27:51'),
(101, 1, 2238, '2025-12-29 21:27:51'),
(102, 1, 2239, '2025-12-29 21:27:51'),
(103, 1, 2240, '2025-12-29 21:27:51'),
(104, 1, 2241, '2025-12-29 21:27:51'),
(105, 1, 2242, '2025-12-29 21:27:51'),
(208, 6, 1852, '2025-12-29 22:09:49'),
(209, 6, 1848, '2025-12-29 22:09:49'),
(210, 6, 1851, '2025-12-29 22:09:49'),
(211, 6, 1850, '2025-12-29 22:09:49'),
(212, 6, 1849, '2025-12-29 22:09:49'),
(213, 6, 1847, '2025-12-29 22:09:49'),
(218, 8, 1859, '2025-12-29 22:44:03'),
(219, 3, 1852, '2025-12-30 14:54:35'),
(220, 3, 1848, '2025-12-30 14:54:35'),
(221, 3, 1851, '2025-12-30 14:54:35'),
(222, 3, 1850, '2025-12-30 14:54:35'),
(223, 3, 1849, '2025-12-30 14:54:35'),
(224, 3, 1847, '2025-12-30 14:54:35'),
(225, 1, 2247, '2025-12-31 09:29:02'),
(226, 1, 2248, '2025-12-31 09:29:02'),
(227, 1, 2249, '2025-12-31 09:29:02'),
(228, 2, 2247, '2025-12-31 09:29:02'),
(229, 2, 2248, '2025-12-31 09:29:02'),
(230, 2, 2249, '2025-12-31 09:29:02'),
(231, 8, 2243, '2025-12-31 09:29:02'),
(232, 8, 2244, '2025-12-31 09:29:02'),
(233, 8, 2245, '2025-12-31 09:29:02'),
(235, 6, 2246, '2025-12-31 09:29:02'),
(295, 5, 1858, '2025-12-31 13:24:21'),
(296, 5, 1857, '2025-12-31 13:24:21'),
(297, 5, 1856, '2025-12-31 13:24:21'),
(298, 5, 1855, '2025-12-31 13:24:21'),
(299, 5, 1854, '2025-12-31 13:24:21'),
(300, 5, 1853, '2025-12-31 13:24:21'),
(301, 5, 2249, '2025-12-31 13:24:21'),
(302, 5, 2246, '2025-12-31 13:24:21'),
(303, 5, 2248, '2025-12-31 13:24:21'),
(304, 5, 2247, '2025-12-31 13:24:21'),
(305, 5, 2234, '2025-12-31 13:24:21'),
(306, 5, 2233, '2025-12-31 13:24:21'),
(307, 5, 2221, '2025-12-31 13:24:21'),
(308, 5, 2218, '2025-12-31 13:24:21'),
(309, 5, 2219, '2025-12-31 13:24:21'),
(310, 5, 2220, '2025-12-31 13:24:21'),
(311, 5, 2232, '2025-12-31 13:24:21'),
(312, 5, 2228, '2025-12-31 13:24:21'),
(313, 5, 2212, '2025-12-31 13:24:21'),
(314, 5, 2209, '2025-12-31 13:24:21'),
(315, 5, 2213, '2025-12-31 13:24:21'),
(316, 5, 2210, '2025-12-31 13:24:21'),
(317, 5, 2211, '2025-12-31 13:24:21'),
(318, 5, 2225, '2025-12-31 13:24:21'),
(319, 5, 2222, '2025-12-31 13:24:21'),
(320, 5, 2226, '2025-12-31 13:24:21'),
(321, 5, 2223, '2025-12-31 13:24:21'),
(322, 5, 2224, '2025-12-31 13:24:21'),
(323, 5, 2231, '2025-12-31 13:24:21'),
(324, 5, 2230, '2025-12-31 13:24:21'),
(325, 5, 2207, '2025-12-31 13:24:21'),
(326, 5, 2204, '2025-12-31 13:24:21'),
(327, 5, 2208, '2025-12-31 13:24:21'),
(328, 5, 2205, '2025-12-31 13:24:21'),
(329, 5, 2206, '2025-12-31 13:24:21'),
(330, 5, 2214, '2025-12-31 13:24:21'),
(331, 5, 2217, '2025-12-31 13:24:21'),
(332, 5, 2215, '2025-12-31 13:24:21'),
(333, 5, 2216, '2025-12-31 13:24:21'),
(334, 5, 1852, '2025-12-31 13:24:21'),
(335, 5, 1848, '2025-12-31 13:24:21'),
(336, 5, 1851, '2025-12-31 13:24:21'),
(337, 5, 1850, '2025-12-31 13:24:21'),
(338, 5, 1849, '2025-12-31 13:24:21'),
(339, 5, 1847, '2025-12-31 13:24:21'),
(340, 5, 1, '2025-12-31 13:24:21'),
(341, 5, 4, '2025-12-31 13:24:21'),
(342, 5, 5, '2025-12-31 13:24:21'),
(343, 5, 2, '2025-12-31 13:24:21'),
(344, 5, 3, '2025-12-31 13:24:21');

-- --------------------------------------------------------

--
-- Table structure for table `router_ip_bindings`
--

CREATE TABLE `router_ip_bindings` (
  `id` int(11) NOT NULL,
  `router_serial_number` varchar(100) NOT NULL COMMENT 'Serial number of the configured router',
  `ip_master_id` int(11) NOT NULL COMMENT 'Reference to ip_master table',
  `configured_by` int(11) NOT NULL COMMENT 'User ID who performed the configuration',
  `configured_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When the configuration was completed',
  `notes` text DEFAULT NULL COMMENT 'Optional notes about the configuration',
  `status` enum('active','unbound') DEFAULT 'active' COMMENT 'Binding status',
  `unbound_by` int(11) DEFAULT NULL COMMENT 'User ID who unbound the IP',
  `unbound_at` timestamp NULL DEFAULT NULL COMMENT 'When the IP was unbound',
  `unbind_reason` text DEFAULT NULL COMMENT 'Reason for unbinding'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Router IP Bindings table for permanent router-to-IP associations';

-- --------------------------------------------------------

--
-- Table structure for table `security_events`
--

CREATE TABLE `security_events` (
  `id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `severity` enum('INFO','WARNING','CRITICAL') DEFAULT 'INFO',
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sites`
--

CREATE TABLE `sites` (
  `id` int(11) NOT NULL,
  `site_name` varchar(255) NOT NULL,
  `lho` varchar(100) NOT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) NOT NULL,
  `country` varchar(100) NOT NULL,
  `zone` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `company_id` int(11) NOT NULL COMMENT 'ADV company that owns the site',
  `status` enum('active','inactive','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_delegations`
--

CREATE TABLE `site_delegations` (
  `id` int(11) NOT NULL,
  `site_id` int(11) NOT NULL,
  `contractor_id` int(11) NOT NULL COMMENT 'Company ID of contractor',
  `delegated_by` int(11) NOT NULL COMMENT 'User ID who delegated',
  `delegated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `rejection_notes` text DEFAULT NULL,
  `responded_by` int(11) DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `states`
--

CREATE TABLE `states` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `country_id` int(11) NOT NULL,
  `zone_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `states`
--

INSERT INTO `states` (`id`, `name`, `country_id`, `zone_id`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'Himachal Pradesh', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(2, 'Punjab', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(3, 'Haryana', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(4, 'Rajasthan', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(5, 'Uttar Pradesh', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(6, 'Uttarakhand', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(7, 'Jammu and Kashmir', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(8, 'Ladakh', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(9, 'Chandigarh', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(10, 'Delhi', 1, 1, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(11, 'Andhra Pradesh', 1, 2, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(12, 'Karnataka', 1, 2, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(13, 'Kerala', 1, 2, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(14, 'Tamil Nadu', 1, 2, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(15, 'Telangana', 1, 2, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(16, 'Puducherry', 1, 2, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(17, 'Lakshadweep', 1, 2, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(18, 'Andaman and Nicobar Islands', 1, 2, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(19, 'Bihar', 1, 3, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(20, 'Jharkhand', 1, 3, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(21, 'Odisha', 1, 3, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(22, 'West Bengal', 1, 3, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(23, 'Goa', 1, 4, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(24, 'Gujarat', 1, 4, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(25, 'Maharashtra', 1, 4, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(26, 'Dadra and Nagar Haveli and Daman and Diu', 1, 4, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL),
(27, 'Madhya Pradesh', 1, 5, 'active', '2025-11-03 00:46:25', '2025-11-03 00:46:25', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `stock`
--

CREATE TABLE `stock` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `quantity` int(11) DEFAULT 0,
  `reserved_quantity` int(11) DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_alerts`
--

CREATE TABLE `stock_alerts` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','overdue_repair') NOT NULL,
  `current_value` int(11) DEFAULT NULL COMMENT 'Current stock quantity or days overdue',
  `threshold_value` int(11) DEFAULT NULL COMMENT 'Threshold that triggered the alert',
  `status` enum('active','cleared') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `cleared_at` timestamp NULL DEFAULT NULL,
  `cleared_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stock_thresholds`
--

CREATE TABLE `stock_thresholds` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `warehouse_id` int(11) DEFAULT NULL COMMENT 'NULL means applies to all warehouses for this product',
  `threshold_quantity` int(11) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfers`
--

CREATE TABLE `transfers` (
  `id` int(11) NOT NULL,
  `transfer_number` varchar(50) NOT NULL,
  `from_warehouse_id` int(11) NOT NULL,
  `to_warehouse_id` int(11) NOT NULL,
  `transfer_date` date NOT NULL,
  `status` enum('pending','in_transit','completed','cancelled') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transfer_items`
--

CREATE TABLE `transfer_items` (
  `id` int(11) NOT NULL,
  `transfer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL COMMENT 'For serializable items, references specific asset',
  `quantity` int(11) DEFAULT 1 COMMENT 'For non-serializable items, quantity transferred',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `company_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `status` tinyint(1) DEFAULT 1 COMMENT '0=inactive, 1=active, 2=locked',
  `last_login` timestamp NULL DEFAULT NULL,
  `failed_login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `password_changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `company_id`, `role_id`, `status`, `last_login`, `failed_login_attempts`, `locked_until`, `password_changed_at`, `created_at`, `updated_at`) VALUES
(2326, 'admin', 'admin@gmail.com', '$2y$10$Vf3tLuAG0oExel7XDZDGAOplaDFyWDxnX.a2qylH8JnKOOT6Zob9u', 'Aniruddh', 'Vishwakarma', 1, 1, 1, '2025-12-31 05:00:01', 0, NULL, '2025-12-27 10:50:48', '2025-12-27 10:50:48', '2025-12-31 05:00:01'),
(19330, 'AshishD', 'ashish@email.com', '$2y$10$nsrERX3Esla1qEjMDPFHK.oRl0KWeWN9jRc/NKS2gAVT3n8FKmHPy', 'Ashish', 'Dubey', 2, 5, 1, '2025-12-31 06:47:57', 0, NULL, '2025-12-29 22:04:52', '2025-12-29 22:04:52', '2025-12-31 06:47:57'),
(19331, 'sandeepj', 'sandeep@gmail.com', '$2y$10$q8kiMKCu/0lPVFCBiseQ2Oj3gCM371Ozmuw2B4mNuOL9dU4qk1Iyy', 'Sandeep', 'Jaiswal', 2, 8, 1, '2025-12-31 14:20:33', 0, NULL, '2025-12-29 22:42:12', '2025-12-29 22:42:12', '2025-12-31 14:20:33'),
(22032, 'ajityadav', 'ajit@advantagesb.com', '$2y$10$FWT2ivOGyTT0SkfX82TGkunD/NBN2Agf5Y0/Y5qjaNX8GqBcWxU3S', 'Ajit', 'Yadav', 1, 3, 1, '2025-12-31 05:00:23', 0, NULL, '2025-12-30 09:05:22', '2025-12-30 09:05:22', '2025-12-31 05:00:23');

-- --------------------------------------------------------

--
-- Table structure for table `user_2fa`
--

CREATE TABLE `user_2fa` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `secret_key` varchar(255) DEFAULT NULL,
  `is_enabled` tinyint(1) DEFAULT 0,
  `backup_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`backup_codes`)),
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_audit_log`
--

CREATE TABLE `user_audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `performed_by` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `warehouses`
--

CREATE TABLE `warehouses` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `company_id` int(11) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `zones`
--

CREATE TABLE `zones` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `zones`
--

INSERT INTO `zones` (`id`, `name`, `status`, `created_at`, `updated_at`, `created_by`, `updated_by`) VALUES
(1, 'North', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:10', NULL, NULL),
(2, 'South', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:12', NULL, NULL),
(3, 'East', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:20', NULL, NULL),
(4, 'West', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:26', NULL, NULL),
(5, 'Central', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:32', NULL, NULL),
(6, 'Northeast', 'active', '2025-11-01 13:17:44', '2025-11-03 00:42:34', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `api_access_log`
--
ALTER TABLE `api_access_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_endpoint` (`endpoint`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_serial_number` (`serial_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_warehouse_id` (`warehouse_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_working_condition` (`working_condition`),
  ADD KEY `idx_current_holder` (`current_holder_type`,`current_holder_id`),
  ADD KEY `idx_source_warehouse_id` (`source_warehouse_id`);

--
-- Indexes for table `banks`
--
ALTER TABLE `banks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bank_name` (`name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `cities`
--
ALTER TABLE `cities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_city_state` (`name`,`state_id`),
  ADD KEY `idx_state` (`state_id`),
  ADD KEY `idx_zone` (`zone_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_country` (`country_id`);

--
-- Indexes for table `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `company_access_log`
--
ALTER TABLE `company_access_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_target_company_id` (`target_company_id`),
  ADD KEY `idx_access_result` (`access_result`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `company_permissions`
--
ALTER TABLE `company_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `permission_id` (`permission_id`),
  ADD KEY `granted_by` (`granted_by`),
  ADD KEY `revoked_by` (`revoked_by`),
  ADD KEY `idx_company_permission` (`company_id`,`permission_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `configuration_audit_log`
--
ALTER TABLE `configuration_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ip_master_id` (`ip_master_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_router` (`router_serial_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_created` (`action_type`,`created_at`);

--
-- Indexes for table `countries`
--
ALTER TABLE `countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_country_name` (`name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `couriers`
--
ALTER TABLE `couriers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_courier_name` (`name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_customer_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_name` (`name`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_country_id` (`country_id`),
  ADD KEY `idx_state_id` (`state_id`),
  ADD KEY `idx_city_id` (`city_id`);

--
-- Indexes for table `delegation_history`
--
ALTER TABLE `delegation_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_delegation` (`delegation_id`);

--
-- Indexes for table `discrepancies`
--
ALTER TABLE `discrepancies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `resolved_by` (`resolved_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dispatch` (`dispatch_id`),
  ADD KEY `idx_pending_receive` (`pending_receive_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `dispatches`
--
ALTER TABLE `dispatches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_dispatch_number` (`dispatch_number`),
  ADD KEY `acknowledged_by` (`acknowledged_by`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_from_company_id` (`from_company_id`),
  ADD KEY `idx_from_warehouse_id` (`from_warehouse_id`),
  ADD KEY `idx_to_company_id` (`to_company_id`),
  ADD KEY `idx_to_user_id` (`to_user_id`),
  ADD KEY `idx_to_warehouse_id` (`to_warehouse_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_acknowledgment_status` (`acknowledgment_status`),
  ADD KEY `idx_dispatch_date` (`dispatch_date`),
  ADD KEY `idx_receive_status` (`receive_status`);

--
-- Indexes for table `dispatch_chain`
--
ALTER TABLE `dispatch_chain`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_asset` (`asset_id`),
  ADD KEY `idx_product_entity` (`product_id`,`to_entity_type`,`to_entity_id`),
  ADD KEY `idx_dispatch` (`dispatch_id`),
  ADD KEY `idx_from_entity` (`from_entity_type`,`from_entity_id`),
  ADD KEY `idx_to_entity` (`to_entity_type`,`to_entity_id`),
  ADD KEY `idx_sequence` (`asset_id`,`sequence_number`);

--
-- Indexes for table `dispatch_items`
--
ALTER TABLE `dispatch_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dispatch_id` (`dispatch_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_asset_id` (`asset_id`);

--
-- Indexes for table `engineer_assignments`
--
ALTER TABLE `engineer_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_delegation` (`delegation_id`),
  ADD KEY `idx_engineer` (`engineer_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `feasibility_ada`
--
ALTER TABLE `feasibility_ada`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment_ada` (`assignment_id`),
  ADD KEY `idx_assignment` (`assignment_id`);

--
-- Indexes for table `feasibility_checks`
--
ALTER TABLE `feasibility_checks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment_check` (`assignment_id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_approval_status` (`approval_status`);

--
-- Indexes for table `feasibility_eta`
--
ALTER TABLE `feasibility_eta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assignment` (`assignment_id`),
  ADD KEY `idx_current` (`is_current`);

--
-- Indexes for table `feasibility_reviews`
--
ALTER TABLE `feasibility_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_feasibility` (`feasibility_id`),
  ADD KEY `idx_reviewer` (`reviewer_id`),
  ADD KEY `idx_review_type` (`review_type`),
  ADD KEY `idx_reviewed_at` (`reviewed_at`);

--
-- Indexes for table `inventory_audit_log`
--
ALTER TABLE `inventory_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_entity_type` (`entity_type`),
  ADD KEY `idx_entity_id` (`entity_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_from_location` (`from_location_type`,`from_location_id`),
  ADD KEY `idx_to_location` (`to_location_type`,`to_location_id`);

--
-- Indexes for table `inventory_counters`
--
ALTER TABLE `inventory_counters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_counter` (`entity_type`,`entity_id`,`product_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `inventory_notifications`
--
ALTER TABLE `inventory_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dispatch_id` (`dispatch_id`),
  ADD KEY `pending_receive_id` (`pending_receive_id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_notification_type` (`notification_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `ip_locks`
--
ALTER TABLE `ip_locks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `locked_by` (`locked_by`),
  ADD KEY `idx_status_expires` (`status`,`expires_at`),
  ADD KEY `idx_ip_master_status` (`ip_master_id`,`status`),
  ADD KEY `idx_router_serial` (`router_serial_number`);

--
-- Indexes for table `ip_master`
--
ALTER TABLE `ip_master`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip_combination` (`network_ip`,`router_ip`,`site_ip`,`subnet_mask`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `ip_restrictions`
--
ALTER TABLE `ip_restrictions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip` (`ip_address`),
  ADD KEY `idx_restriction_type` (`restriction_type`),
  ADD KEY `idx_expires_at` (`expires_at`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `lhos`
--
ALTER TABLE `lhos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_lho_name` (`lho_name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_identifier` (`identifier`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_success` (`success`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_migration` (`migration`);

--
-- Indexes for table `password_history`
--
ALTER TABLE `password_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `pending_receives`
--
ALTER TABLE `pending_receives`
  ADD PRIMARY KEY (`id`),
  ADD KEY `accepted_by` (`accepted_by`),
  ADD KEY `idx_recipient` (`recipient_type`,`recipient_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dispatch` (`dispatch_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `pending_receive_items`
--
ALTER TABLE `pending_receive_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pending_receive` (`pending_receive_id`),
  ADD KEY `idx_dispatch_item` (`dispatch_item_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `unique_module_action` (`module`,`action`),
  ADD KEY `idx_module` (`module`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_adv_only` (`is_adv_only`);

--
-- Indexes for table `permission_audit_log`
--
ALTER TABLE `permission_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_permission_id` (`permission_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_category_id` (`category_id`),
  ADD KEY `idx_inventory_type` (`inventory_type`),
  ADD KEY `idx_is_serializable` (`is_serializable`),
  ADD KEY `idx_is_repairable` (`is_repairable`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `repairs`
--
ALTER TABLE `repairs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_asset_id` (`asset_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_send_date` (`send_date`),
  ADD KEY `idx_expected_return_date` (`expected_return_date`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_name_company_type` (`name`,`company_type`),
  ADD KEY `idx_company_type` (`company_type`),
  ADD KEY `idx_level` (`level`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Indexes for table `router_ip_bindings`
--
ALTER TABLE `router_ip_bindings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_active_router` (`router_serial_number`,`status`),
  ADD KEY `configured_by` (`configured_by`),
  ADD KEY `unbound_by` (`unbound_by`),
  ADD KEY `idx_router_serial` (`router_serial_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_ip_master` (`ip_master_id`);

--
-- Indexes for table `security_events`
--
ALTER TABLE `security_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `sites`
--
ALTER TABLE `sites`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_site_lho_company` (`site_name`,`lho`,`company_id`),
  ADD KEY `idx_lho` (`lho`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_company` (`company_id`);

--
-- Indexes for table `site_delegations`
--
ALTER TABLE `site_delegations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_site` (`site_id`),
  ADD KEY `idx_contractor` (`contractor_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_delegated_at` (`delegated_at`);

--
-- Indexes for table `states`
--
ALTER TABLE `states`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_state_country` (`name`,`country_id`),
  ADD KEY `idx_country` (`country_id`),
  ADD KEY `idx_zone` (`zone_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `stock`
--
ALTER TABLE `stock`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_product_warehouse` (`product_id`,`warehouse_id`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_warehouse_id` (`warehouse_id`);

--
-- Indexes for table `stock_alerts`
--
ALTER TABLE `stock_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cleared_by` (`cleared_by`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_warehouse_id` (`warehouse_id`),
  ADD KEY `idx_alert_type` (`alert_type`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `stock_thresholds`
--
ALTER TABLE `stock_thresholds`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_threshold` (`product_id`,`warehouse_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_warehouse_id` (`warehouse_id`);

--
-- Indexes for table `transfers`
--
ALTER TABLE `transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_transfer_number` (`transfer_number`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_from_warehouse_id` (`from_warehouse_id`),
  ADD KEY `idx_to_warehouse_id` (`to_warehouse_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_transfer_date` (`transfer_date`);

--
-- Indexes for table `transfer_items`
--
ALTER TABLE `transfer_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_transfer_id` (`transfer_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_asset_id` (`asset_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_role_id` (`role_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `user_2fa`
--
ALTER TABLE `user_2fa`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user` (`user_id`);

--
-- Indexes for table `user_audit_log`
--
ALTER TABLE `user_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `performed_by` (`performed_by`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_target_user_id` (`target_user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_session_token` (`session_token`),
  ADD KEY `idx_expires_at` (`expires_at`);

--
-- Indexes for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_warehouse_name_company` (`name`,`company_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_company_id` (`company_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `zones`
--
ALTER TABLE `zones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_zone_name` (`name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `api_access_log`
--
ALTER TABLE `api_access_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `banks`
--
ALTER TABLE `banks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cities`
--
ALTER TABLE `cities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=602;

--
-- AUTO_INCREMENT for table `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2083;

--
-- AUTO_INCREMENT for table `company_access_log`
--
ALTER TABLE `company_access_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5204;

--
-- AUTO_INCREMENT for table `company_permissions`
--
ALTER TABLE `company_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `configuration_audit_log`
--
ALTER TABLE `configuration_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `countries`
--
ALTER TABLE `countries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `couriers`
--
ALTER TABLE `couriers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `delegation_history`
--
ALTER TABLE `delegation_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `discrepancies`
--
ALTER TABLE `discrepancies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dispatches`
--
ALTER TABLE `dispatches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dispatch_chain`
--
ALTER TABLE `dispatch_chain`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dispatch_items`
--
ALTER TABLE `dispatch_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `engineer_assignments`
--
ALTER TABLE `engineer_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feasibility_ada`
--
ALTER TABLE `feasibility_ada`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feasibility_checks`
--
ALTER TABLE `feasibility_checks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feasibility_eta`
--
ALTER TABLE `feasibility_eta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feasibility_reviews`
--
ALTER TABLE `feasibility_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_audit_log`
--
ALTER TABLE `inventory_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_counters`
--
ALTER TABLE `inventory_counters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory_notifications`
--
ALTER TABLE `inventory_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ip_locks`
--
ALTER TABLE `ip_locks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ip_master`
--
ALTER TABLE `ip_master`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8523;

--
-- AUTO_INCREMENT for table `ip_restrictions`
--
ALTER TABLE `ip_restrictions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `lhos`
--
ALTER TABLE `lhos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `password_history`
--
ALTER TABLE `password_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_receives`
--
ALTER TABLE `pending_receives`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pending_receive_items`
--
ALTER TABLE `pending_receive_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2250;

--
-- AUTO_INCREMENT for table `permission_audit_log`
--
ALTER TABLE `permission_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `repairs`
--
ALTER TABLE `repairs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2746;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=345;

--
-- AUTO_INCREMENT for table `router_ip_bindings`
--
ALTER TABLE `router_ip_bindings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_events`
--
ALTER TABLE `security_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sites`
--
ALTER TABLE `sites`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_delegations`
--
ALTER TABLE `site_delegations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `states`
--
ALTER TABLE `states`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `stock`
--
ALTER TABLE `stock`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1401;

--
-- AUTO_INCREMENT for table `stock_alerts`
--
ALTER TABLE `stock_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stock_thresholds`
--
ALTER TABLE `stock_thresholds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transfers`
--
ALTER TABLE `transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=801;

--
-- AUTO_INCREMENT for table `transfer_items`
--
ALTER TABLE `transfer_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1190;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22113;

--
-- AUTO_INCREMENT for table `user_2fa`
--
ALTER TABLE `user_2fa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_audit_log`
--
ALTER TABLE `user_audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `warehouses`
--
ALTER TABLE `warehouses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `zones`
--
ALTER TABLE `zones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `api_access_log`
--
ALTER TABLE `api_access_log`
  ADD CONSTRAINT `api_access_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `assets_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `assets_ibfk_3` FOREIGN KEY (`source_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `assets_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `assets_ibfk_5` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `banks`
--
ALTER TABLE `banks`
  ADD CONSTRAINT `banks_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `banks_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `cities`
--
ALTER TABLE `cities`
  ADD CONSTRAINT `cities_ibfk_1` FOREIGN KEY (`state_id`) REFERENCES `states` (`id`),
  ADD CONSTRAINT `cities_ibfk_2` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cities_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cities_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cities_country` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`);

--
-- Constraints for table `company_access_log`
--
ALTER TABLE `company_access_log`
  ADD CONSTRAINT `company_access_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `company_access_log_ibfk_2` FOREIGN KEY (`target_company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `company_permissions`
--
ALTER TABLE `company_permissions`
  ADD CONSTRAINT `company_permissions_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `company_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `company_permissions_ibfk_3` FOREIGN KEY (`granted_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `company_permissions_ibfk_4` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `configuration_audit_log`
--
ALTER TABLE `configuration_audit_log`
  ADD CONSTRAINT `configuration_audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `configuration_audit_log_ibfk_2` FOREIGN KEY (`ip_master_id`) REFERENCES `ip_master` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `countries`
--
ALTER TABLE `countries`
  ADD CONSTRAINT `countries_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `countries_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `couriers`
--
ALTER TABLE `couriers`
  ADD CONSTRAINT `couriers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `couriers_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `discrepancies`
--
ALTER TABLE `discrepancies`
  ADD CONSTRAINT `discrepancies_ibfk_1` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `discrepancies_ibfk_2` FOREIGN KEY (`pending_receive_id`) REFERENCES `pending_receives` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `discrepancies_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `discrepancies_ibfk_4` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `discrepancies_ibfk_5` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dispatches`
--
ALTER TABLE `dispatches`
  ADD CONSTRAINT `dispatches_ibfk_1` FOREIGN KEY (`from_company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `dispatches_ibfk_2` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `dispatches_ibfk_3` FOREIGN KEY (`to_company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dispatches_ibfk_4` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dispatches_ibfk_5` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dispatches_ibfk_6` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dispatches_ibfk_7` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `dispatch_chain`
--
ALTER TABLE `dispatch_chain`
  ADD CONSTRAINT `dispatch_chain_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `dispatch_chain_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `dispatch_chain_ibfk_3` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dispatch_items`
--
ALTER TABLE `dispatch_items`
  ADD CONSTRAINT `dispatch_items_ibfk_1` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dispatch_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `dispatch_items_ibfk_3` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `feasibility_reviews`
--
ALTER TABLE `feasibility_reviews`
  ADD CONSTRAINT `feasibility_reviews_ibfk_1` FOREIGN KEY (`feasibility_id`) REFERENCES `feasibility_checks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feasibility_reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_audit_log`
--
ALTER TABLE `inventory_audit_log`
  ADD CONSTRAINT `inventory_audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `inventory_counters`
--
ALTER TABLE `inventory_counters`
  ADD CONSTRAINT `inventory_counters_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `inventory_notifications`
--
ALTER TABLE `inventory_notifications`
  ADD CONSTRAINT `inventory_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_notifications_ibfk_2` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `inventory_notifications_ibfk_3` FOREIGN KEY (`pending_receive_id`) REFERENCES `pending_receives` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ip_locks`
--
ALTER TABLE `ip_locks`
  ADD CONSTRAINT `ip_locks_ibfk_1` FOREIGN KEY (`ip_master_id`) REFERENCES `ip_master` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ip_locks_ibfk_2` FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ip_master`
--
ALTER TABLE `ip_master`
  ADD CONSTRAINT `ip_master_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `ip_restrictions`
--
ALTER TABLE `ip_restrictions`
  ADD CONSTRAINT `ip_restrictions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `password_history`
--
ALTER TABLE `password_history`
  ADD CONSTRAINT `password_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `pending_receives`
--
ALTER TABLE `pending_receives`
  ADD CONSTRAINT `pending_receives_ibfk_1` FOREIGN KEY (`dispatch_id`) REFERENCES `dispatches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pending_receives_ibfk_2` FOREIGN KEY (`accepted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `pending_receive_items`
--
ALTER TABLE `pending_receive_items`
  ADD CONSTRAINT `pending_receive_items_ibfk_1` FOREIGN KEY (`pending_receive_id`) REFERENCES `pending_receives` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `pending_receive_items_ibfk_2` FOREIGN KEY (`dispatch_item_id`) REFERENCES `dispatch_items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `permission_audit_log`
--
ALTER TABLE `permission_audit_log`
  ADD CONSTRAINT `permission_audit_log_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `permission_audit_log_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `permission_audit_log_ibfk_3` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD CONSTRAINT `product_categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_categories_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_categories_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `repairs`
--
ALTER TABLE `repairs`
  ADD CONSTRAINT `repairs_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`),
  ADD CONSTRAINT `repairs_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `repairs_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `router_ip_bindings`
--
ALTER TABLE `router_ip_bindings`
  ADD CONSTRAINT `router_ip_bindings_ibfk_1` FOREIGN KEY (`ip_master_id`) REFERENCES `ip_master` (`id`),
  ADD CONSTRAINT `router_ip_bindings_ibfk_2` FOREIGN KEY (`configured_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `router_ip_bindings_ibfk_3` FOREIGN KEY (`unbound_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `security_events`
--
ALTER TABLE `security_events`
  ADD CONSTRAINT `security_events_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `states`
--
ALTER TABLE `states`
  ADD CONSTRAINT `states_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  ADD CONSTRAINT `states_ibfk_2` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `states_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `states_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock`
--
ALTER TABLE `stock`
  ADD CONSTRAINT `stock_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `stock_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `stock_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_alerts`
--
ALTER TABLE `stock_alerts`
  ADD CONSTRAINT `stock_alerts_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_alerts_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_alerts_ibfk_3` FOREIGN KEY (`cleared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stock_thresholds`
--
ALTER TABLE `stock_thresholds`
  ADD CONSTRAINT `stock_thresholds_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_thresholds_ibfk_2` FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stock_thresholds_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stock_thresholds_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transfers`
--
ALTER TABLE `transfers`
  ADD CONSTRAINT `transfers_ibfk_1` FOREIGN KEY (`from_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `transfers_ibfk_2` FOREIGN KEY (`to_warehouse_id`) REFERENCES `warehouses` (`id`),
  ADD CONSTRAINT `transfers_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transfers_ibfk_4` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transfer_items`
--
ALTER TABLE `transfer_items`
  ADD CONSTRAINT `transfer_items_ibfk_1` FOREIGN KEY (`transfer_id`) REFERENCES `transfers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `transfer_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `transfer_items_ibfk_3` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);

--
-- Constraints for table `user_2fa`
--
ALTER TABLE `user_2fa`
  ADD CONSTRAINT `user_2fa_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_audit_log`
--
ALTER TABLE `user_audit_log`
  ADD CONSTRAINT `user_audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_audit_log_ibfk_2` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `user_audit_log_ibfk_3` FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `warehouses`
--
ALTER TABLE `warehouses`
  ADD CONSTRAINT `warehouses_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `warehouses_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `warehouses_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `zones`
--
ALTER TABLE `zones`
  ADD CONSTRAINT `zones_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `zones_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

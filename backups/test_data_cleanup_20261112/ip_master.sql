-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: if0_40845939_clarity_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ip_master`
--

DROP TABLE IF EXISTS `ip_master`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `network_ip` varchar(15) NOT NULL COMMENT 'Network IP address',
  `router_ip` varchar(15) NOT NULL COMMENT 'Router IP address',
  `site_ip` varchar(15) NOT NULL COMMENT 'Site IP address',
  `subnet_mask` varchar(15) NOT NULL COMMENT 'Subnet mask',
  `status` enum('available','locked','configured') DEFAULT 'available' COMMENT 'Current status of IP combination',
  `created_by` int(11) DEFAULT NULL COMMENT 'User ID who created this record',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_ip_combination` (`network_ip`,`router_ip`,`site_ip`,`subnet_mask`),
  KEY `idx_status` (`status`),
  KEY `created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=3348 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP Master table storing unique IP address combinations for router configuration';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_master`
--

LOCK TABLES `ip_master` WRITE;
/*!40000 ALTER TABLE `ip_master` DISABLE KEYS */;
INSERT INTO `ip_master` VALUES (9,'10.44.206.193','192.163.163.193','172.18.242.193','255.255.255.0','locked',80326,'2026-10-28 15:30:10','2026-10-28 15:30:14'),(22,'10.5.230.248','192.114.59.248','172.24.68.248','255.255.255.0','locked',80326,'2026-10-28 15:30:11','2026-10-28 15:30:26'),(163,'96.118.153.221','33.232.184.243','193.169.24.47','255.255.255.114','available',NULL,'2026-10-28 15:30:12','2026-10-28 15:31:35'),(168,'252.247.113.205','158.29.180.9','157.166.138.120','255.255.255.0','available',80326,'2026-10-28 15:30:12','2026-10-28 15:30:12'),(259,'203.141.150.157','121.168.71.161','136.9.134.98','255.255.255.0','available',2326,'2026-10-28 15:30:14','2026-10-28 15:30:14'),(260,'174.246.186.83','181.39.38.181','130.101.52.106','255.255.255.0','available',2326,'2026-10-28 15:30:14','2026-10-28 15:30:14'),(263,'75.118.202.161','195.47.192.112','106.197.162.10','255.255.255.0','available',2326,'2026-10-28 15:30:14','2026-10-28 15:30:14'),(267,'121.4.179.210','77.192.231.21','86.136.55.145','255.255.255.0','available',2326,'2026-10-28 15:30:14','2026-10-28 15:30:14'),(268,'65.126.137.235','197.75.68.28','128.19.50.147','255.255.255.0','available',2326,'2026-10-28 15:30:14','2026-10-28 15:30:14');
/*!40000 ALTER TABLE `ip_master` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-12 15:51:55

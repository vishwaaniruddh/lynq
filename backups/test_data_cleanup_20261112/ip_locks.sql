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
-- Table structure for table `ip_locks`
--

DROP TABLE IF EXISTS `ip_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ip_locks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_master_id` int(11) NOT NULL COMMENT 'Reference to ip_master table',
  `router_serial_number` varchar(100) NOT NULL COMMENT 'Serial number of router being configured',
  `locked_by` int(11) NOT NULL COMMENT 'User ID who acquired the lock',
  `locked_at` datetime DEFAULT current_timestamp() COMMENT 'When the lock was acquired',
  `expires_at` datetime NOT NULL COMMENT 'When the lock expires (locked_at + 20 minutes)',
  `status` enum('active','released','expired') DEFAULT 'active' COMMENT 'Current lock status',
  `released_at` datetime DEFAULT NULL COMMENT 'When the lock was released (if applicable)',
  PRIMARY KEY (`id`),
  KEY `locked_by` (`locked_by`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_ip_master_status` (`ip_master_id`,`status`),
  KEY `idx_router_serial` (`router_serial_number`)
) ENGINE=InnoDB AUTO_INCREMENT=590 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='IP Locks table for temporary locking during 20-minute configuration process';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ip_locks`
--

LOCK TABLES `ip_locks` WRITE;
/*!40000 ALTER TABLE `ip_locks` DISABLE KEYS */;
INSERT INTO `ip_locks` VALUES (1,11,'RTR-CONC-1vEePSgj-4941',19330,'2026-10-28 21:00:14','2026-10-28 21:20:14','expired',NULL);
/*!40000 ALTER TABLE `ip_locks` ENABLE KEYS */;
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

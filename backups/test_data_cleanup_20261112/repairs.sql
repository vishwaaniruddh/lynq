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
-- Table structure for table `repairs`
--

DROP TABLE IF EXISTS `repairs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `repairs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `updated_by` (`updated_by`),
  KEY `idx_asset_id` (`asset_id`),
  KEY `idx_status` (`status`),
  KEY `idx_send_date` (`send_date`),
  KEY `idx_expected_return_date` (`expected_return_date`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `repairs`
--

LOCK TABLES `repairs` WRITE;
/*!40000 ALTER TABLE `repairs` DISABLE KEYS */;
INSERT INTO `repairs` VALUES (8,940,'Test Vendor Ss2Ox',312.00,80.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:37','2026-10-28 15:30:38'),(9,960,'Test Vendor PFgUZ',441.00,71.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:38','2026-10-28 15:30:38'),(10,972,'Test Vendor pjBee',989.00,60.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:38','2026-10-28 15:30:38'),(11,987,'Test Vendor mS27p',780.00,149.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:39','2026-10-28 15:30:39'),(12,1012,'Test Vendor iTsBZ',219.00,207.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:39','2026-10-28 15:30:39'),(13,1026,'Test Vendor oCNvt',761.00,361.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:40','2026-10-28 15:30:40'),(14,1045,'Test Vendor vz3zn',847.00,104.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:40','2026-10-28 15:30:40'),(15,1071,'Test Vendor VzO1o',886.00,141.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:40','2026-10-28 15:30:40'),(16,1091,'Test Vendor vqaes',604.00,433.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:41','2026-10-28 15:30:41'),(17,1107,'Test Vendor lA6J7',827.00,496.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:41','2026-10-28 15:30:41'),(18,1126,'Test Vendor 9DgzS',125.00,496.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:41','2026-10-28 15:30:42'),(19,1151,'Test Vendor 5asrs',188.00,372.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:42','2026-10-28 15:30:42'),(20,1169,'Test Vendor 5aMTt',275.00,483.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:42','2026-10-28 15:30:42'),(21,1186,'Test Vendor jT0Fj',727.00,252.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:43','2026-10-28 15:30:43'),(22,1198,'Test Vendor gU9qP',457.00,292.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:43','2026-10-28 15:30:43'),(23,1215,'Test Vendor sgBQc',603.00,52.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:43','2026-10-28 15:30:43'),(24,1231,'Test Vendor N9aE7',780.00,365.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:44','2026-10-28 15:30:44'),(25,1249,'Test Vendor vEd5S',713.00,189.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:44','2026-10-28 15:30:44'),(26,1263,'Test Vendor RXWr4',596.00,130.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:44','2026-10-28 15:30:44'),(27,1277,'Test Vendor kqM19',556.00,173.00,'2026-10-28','2026-11-04','2026-10-28','completed',NULL,NULL,'Repaired successfully',NULL,NULL,'2026-10-28 15:30:45','2026-10-28 15:30:45');
/*!40000 ALTER TABLE `repairs` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-12 15:51:53

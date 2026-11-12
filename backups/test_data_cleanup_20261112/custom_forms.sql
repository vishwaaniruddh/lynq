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
-- Table structure for table `custom_forms`
--

DROP TABLE IF EXISTS `custom_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `custom_forms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `form_code` varchar(50) NOT NULL,
  `form_name` varchar(255) NOT NULL,
  `purpose` varchar(50) NOT NULL COMMENT 'site_add, feasibility, installation, other',
  `project_id` int(11) DEFAULT NULL,
  `customer_id` int(11) DEFAULT NULL COMMENT 'NULL means Global / Default for this purpose',
  `description` text DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `status` enum('active','inactive','draft') NOT NULL DEFAULT 'active',
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `company_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `form_code` (`form_code`),
  KEY `idx_cf_purpose` (`purpose`),
  KEY `idx_cf_customer` (`customer_id`),
  KEY `idx_cf_status` (`status`),
  KEY `idx_cf_company` (`company_id`),
  KEY `idx_cf_project` (`project_id`),
  CONSTRAINT `fk_cf_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_cf_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `custom_forms`
--

LOCK TABLES `custom_forms` WRITE;
/*!40000 ALTER TABLE `custom_forms` DISABLE KEYS */;
INSERT INTO `custom_forms` VALUES (1,'FORM_SITE_ADD_XTPL_01','XTPL Site Addition Form','site_add',1,NULL,NULL,1,'active',0,1,1,1,'2026-08-24 19:10:48','2026-09-13 03:44:13',NULL),(4,'FORM_SITE_ADD_GLOBAL_260826125250','Diebold Site Addition Form (Updated)','site_add',NULL,NULL,NULL,1,'active',0,1,1,1,'2026-08-26 07:22:50','2026-08-26 07:22:50','2026-08-26 07:22:50'),(6,'FORM_SITE_ADD_GLOBAL_260826125312','Diebold Site Addition Form (Updated)','site_add',NULL,NULL,NULL,1,'active',0,1,1,1,'2026-08-26 07:23:12','2026-08-26 07:23:12','2026-08-26 07:23:12'),(10,'FORM_SITE_ADD_PROJ_1_260828020745','Diebold Site Addition Form (Updated)','site_add',NULL,NULL,NULL,1,'active',0,1,1,1,'2026-08-27 20:37:45','2026-08-27 20:37:45','2026-08-27 20:37:45'),(12,'TEST_FORM_MASTER_1788150377','Master Synced Test Form','site_add',1,NULL,'Form with master-synced bank dropdown',1,'active',0,1,1,NULL,'2026-08-31 04:26:17','2026-09-16 11:48:21','2026-09-16 11:48:21'),(15,'FORM_SITE_ADD_PROJ_1_260831184302','Diebold Site Addition Form (Updated)','site_add',NULL,NULL,NULL,1,'active',0,1,1,1,'2026-08-31 13:13:02','2026-08-31 13:13:02','2026-08-31 13:13:02'),(17,'TEST_FORM_MASTER_1789271046','Master Synced Test Form','site_add',1,NULL,'Form with master-synced bank dropdown',1,'active',0,1,1,NULL,'2026-09-13 03:44:06','2026-09-16 11:48:24','2026-09-16 11:48:24'),(18,'FORM__FEASIBILITY','Feasibility Survey Form','feasibility',1,NULL,'Comprehensive feasibility survey form with 7 sections and 50 fields covering ATM info, Network signal checks, Power/UPS, Electrical voltage, Site access, Environment and Inspection remarks.',1,'active',0,1,1,2326,'2026-09-16 11:57:10','2026-10-12 06:50:04',NULL),(19,'FORM_INSTALLATION_XTPL_01','Installation Checklist Form','installation',1,NULL,NULL,1,'active',1,NULL,1,NULL,'2026-09-16 19:43:49','2026-10-28 15:30:09',NULL),(20,'FORM_INSTALLATION_PROJ_1_261012130843','XTPL Installation Checklist Form','installation',1,NULL,NULL,1,'active',0,1,2326,NULL,'2026-10-12 07:38:43','2026-10-12 07:38:43',NULL);
/*!40000 ALTER TABLE `custom_forms` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-12 15:51:54

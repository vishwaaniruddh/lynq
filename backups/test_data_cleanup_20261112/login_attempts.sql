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
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  `failure_reason` varchar(100) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_success` (`success`)
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (19,'admin','10.63.21.7',1,NULL,'node','2026-10-29 05:54:03'),(20,'admin','10.63.21.7',1,NULL,'node','2026-10-29 06:55:24'),(21,'admin','10.63.21.7',0,'INVALID_PASSWORD','node','2026-10-29 08:26:49'),(22,'admin','10.63.21.7',1,NULL,'node','2026-10-30 09:50:00'),(23,'Anisha@skyionx.com','122.170.118.132',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-31 05:38:12'),(24,'admin','10.63.21.7',1,NULL,'node','2026-10-31 07:25:34'),(25,'admin','10.63.21.7',1,NULL,'node','2026-10-31 07:59:23'),(26,'admin','10.63.21.7',1,NULL,'node','2026-10-31 08:34:14'),(27,'admin','10.63.21.7',1,NULL,'node','2026-10-31 10:58:08'),(28,'admin','10.63.21.7',1,NULL,'node','2026-10-31 11:32:39'),(29,'admin','10.63.21.7',1,NULL,'node','2026-11-01 06:02:45'),(30,'admin','10.63.21.7',1,NULL,'node','2026-11-02 05:08:52'),(31,'admin','10.63.21.7',1,NULL,'node','2026-11-02 05:39:45'),(32,'admin','10.63.21.7',1,NULL,'node','2026-11-02 06:22:44'),(33,'admin','10.63.21.7',1,NULL,'node','2026-11-02 06:49:20'),(34,'admin','10.63.21.7',1,NULL,'node','2026-11-02 07:49:52'),(35,'admin','10.63.21.7',1,NULL,'node','2026-11-02 11:22:33'),(36,'admin','10.63.21.7',1,NULL,'node','2026-11-02 13:02:55'),(37,'admin','10.63.21.7',1,NULL,'node','2026-11-03 01:55:58'),(38,'admin','10.63.21.7',1,NULL,'node','2026-11-03 06:08:00'),(39,'admin','10.63.21.7',1,NULL,'node','2026-11-03 07:11:04'),(40,'admin','10.63.21.7',1,NULL,'node','2026-11-03 08:40:28'),(41,'admin','10.63.21.7',1,NULL,'node','2026-11-03 09:58:35'),(42,'admin','10.63.21.7',1,NULL,'node','2026-11-03 10:55:32'),(43,'admin','10.63.21.7',1,NULL,'node','2026-11-03 11:44:02'),(44,'admin','10.63.21.7',1,NULL,'node','2026-11-03 23:14:04'),(45,'admin','10.63.21.7',1,NULL,'node','2026-11-04 05:46:15'),(46,'admin','10.63.21.7',1,NULL,'node','2026-11-04 05:59:10'),(47,'admin','10.63.21.7',1,NULL,'node','2026-11-04 05:59:43'),(48,'admin','10.63.21.7',1,NULL,'node','2026-11-04 07:12:14'),(49,'admin','10.63.21.7',1,NULL,'node','2026-11-04 08:07:46'),(50,'admin','10.63.21.7',1,NULL,'node','2026-11-04 08:38:07'),(51,'admin','10.63.21.7',1,NULL,'node','2026-11-04 09:17:22'),(52,'admin','10.63.21.7',1,NULL,'node','2026-11-04 10:08:08'),(53,'admin','10.63.21.7',1,NULL,'node','2026-11-04 13:21:22'),(54,'admin','10.63.21.7',1,NULL,'node','2026-11-05 04:02:13'),(55,'admin','10.63.21.7',1,NULL,'node','2026-11-05 04:47:10'),(56,'admin','10.63.21.7',1,NULL,'node','2026-11-05 06:47:20'),(57,'admin','10.63.21.7',1,NULL,'node','2026-11-05 07:14:14'),(58,'admin','10.63.21.7',1,NULL,'node','2026-11-05 08:10:51'),(59,'admin','10.63.21.7',1,NULL,'node','2026-11-05 08:50:14'),(60,'admin','10.63.21.7',1,NULL,'node','2026-11-05 09:17:48'),(61,'admin','10.63.21.7',1,NULL,'node','2026-11-05 11:09:57'),(62,'admin','10.63.21.7',1,NULL,'node','2026-11-06 06:34:35'),(63,'admin','10.63.21.7',1,NULL,'node','2026-11-06 08:37:50'),(64,'admin','10.63.21.7',1,NULL,'node','2026-11-06 10:01:00'),(66,'pallavi@skyionx.com','10.63.21.7',0,'INVALID_PASSWORD','node','2026-11-06 10:06:39'),(67,'pallavi@skyionx.com','10.63.21.7',0,'INVALID_PASSWORD','node','2026-11-06 10:07:01'),(68,'pallavi@skyionx.com','10.63.21.7',0,'INVALID_PASSWORD','node','2026-11-06 10:07:10'),(69,'pallavi@skyionx.com','10.63.21.7',0,'INVALID_PASSWORD','node','2026-11-06 10:07:14'),(70,'admin','10.63.21.7',1,NULL,'node','2026-11-06 11:39:40'),(71,'admin','10.63.21.7',1,NULL,'node','2026-11-06 12:44:23'),(72,'admin','10.63.21.7',1,NULL,'node','2026-11-07 08:50:29'),(73,'admin','10.63.21.7',1,NULL,'node','2026-11-07 09:38:29'),(74,'admin','10.63.21.7',1,NULL,'node','2026-11-09 08:35:00'),(75,'admin','10.63.21.7',1,NULL,'node','2026-11-11 06:02:27'),(76,'admin','122.170.118.132',1,NULL,'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:159.0) Gecko/20100101 Firefox/159.0','2026-11-11 09:01:40'),(82,'admin','49.204.164.184',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 10:49:29'),(89,'admin','127.0.0.1',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-11-11 10:54:41'),(90,'admin','::1',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/154.0.0.0 Safari/537.36','2026-11-11 10:55:41'),(91,'admin','::1',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 10:57:16'),(92,'admin','::1',0,'INVALID_PASSWORD','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 10:57:30'),(93,'admin','::1',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 10:57:36'),(94,'admin','::1',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 11:35:57'),(95,'omkar@skyionx.com','::1',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-11 11:36:23'),(96,'Omkar','122.170.118.132',0,'USER_NOT_FOUND','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-11-12 05:03:28'),(97,'Omkar','122.170.118.132',0,'USER_NOT_FOUND','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-11-12 05:03:30'),(98,'Omkar@skyionx.com','122.170.118.132',0,'INVALID_PASSWORD','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-11-12 05:03:37'),(99,'Omkar@skyionx.com','122.170.118.132',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36','2026-11-12 05:03:40'),(100,'admin','10.63.21.7',1,NULL,'node','2026-11-12 05:11:16'),(101,'admin','10.63.21.7',1,NULL,'node','2026-11-12 05:34:16'),(102,'vikaspal@gmail.com','122.170.118.132',0,'USER_NOT_FOUND','okhttp/4.9.2','2026-11-12 05:45:56'),(103,'anisha@skyionx.com','122.170.118.132',1,NULL,'okhttp/4.9.2','2026-11-12 05:47:31'),(104,'admin','10.63.21.7',0,'INVALID_PASSWORD','node','2026-11-12 09:24:40'),(105,'admin','10.63.21.7',1,NULL,'node','2026-11-12 09:24:47'),(106,'admin','10.63.21.7',1,NULL,'node','2026-11-12 09:31:07'),(107,'admin','10.63.21.7',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36','2026-11-12 09:31:25'),(108,'Faraz','10.63.21.7',0,'USER_NOT_FOUND','node','2026-11-12 09:31:36'),(109,'pallavi@skyionx.com','10.63.21.7',0,'INVALID_PASSWORD','node','2026-11-12 09:34:20'),(110,'pallavi@skyionx.com','10.63.21.7',1,NULL,'node','2026-11-12 09:34:36'),(111,'anisha@gmail.com','49.204.164.184',0,'INVALID_PASSWORD','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 09:35:08'),(112,'anisha@gmail.com','49.204.164.184',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36','2026-11-12 09:35:39'),(113,'Anisha@skyionx.com','122.170.118.132',1,NULL,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-11-12 09:56:01'),(114,'admin','10.63.21.7',1,NULL,'node','2026-11-12 10:19:06');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-11-12 15:51:56

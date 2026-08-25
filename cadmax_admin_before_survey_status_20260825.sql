-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: cadmax_admin
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
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `user_role` enum('admin','doer') NOT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `action_time` datetime NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_logs`
--

LOCK TABLES `activity_logs` WRITE;
/*!40000 ALTER TABLE `activity_logs` DISABLE KEYS */;
INSERT INTO `activity_logs` VALUES (1,1,'doer','login','User logged in via admin guard','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 07:46:36','2026-07-21 02:16:36','2026-07-21 02:16:36'),(2,1,'doer','login','User logged in via admin guard','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 07:46:55','2026-07-21 02:16:55','2026-07-21 02:16:55'),(3,4,'doer','login','User logged in via member guard','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-21 08:25:51','2026-07-21 02:55:51','2026-07-21 02:55:51'),(4,5,'doer','login','User logged in via admin guard','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-22 08:45:05','2026-07-22 03:15:05','2026-07-22 03:15:05'),(5,6,'doer','login','Member logged in via API (Postman Testing)','127.0.0.1','PostmanRuntime/7.54.0','2026-07-31 12:25:34','2026-07-31 06:55:34','2026-07-31 06:55:34'),(6,6,'doer','login','Member logged in via API (Postman Testing)','127.0.0.1','PostmanRuntime/7.54.0','2026-07-31 12:25:53','2026-07-31 06:55:53','2026-07-31 06:55:53'),(7,6,'doer','login','Member logged in via API (Postman Testing)','127.0.0.1','PostmanRuntime/7.54.0','2026-07-31 12:26:25','2026-07-31 06:56:25','2026-07-31 06:56:25'),(8,6,'doer','login','Member logged in via API (Postman Testing)','127.0.0.1','PostmanRuntime/7.54.0','2026-07-31 12:26:29','2026-07-31 06:56:29','2026-07-31 06:56:29'),(9,6,'doer','login','Member logged in via API (Postman Testing)','127.0.0.1','PostmanRuntime/7.54.0','2026-07-31 12:30:06','2026-07-31 07:00:06','2026-07-31 07:00:06'),(10,6,'doer','login','Member logged in via OTP verification (Mobile App)','127.0.0.1','PostmanRuntime/7.54.0','2026-07-31 12:35:34','2026-07-31 07:05:34','2026-07-31 07:05:34'),(11,6,'doer','login','Member logged in via API (Mobile App)','127.0.0.1','PostmanRuntime/7.54.0','2026-07-31 12:37:18','2026-07-31 07:07:18','2026-07-31 07:07:18'),(12,6,'doer','logout','Member logged out from API (Mobile App)','127.0.0.1','PostmanRuntime/7.54.0','2026-07-31 12:47:45','2026-07-31 07:17:45','2026-07-31 07:17:45'),(13,6,'doer','login','User logged in via admin guard','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-11 06:53:44','2026-08-11 01:23:44','2026-08-11 01:23:44'),(14,6,'doer','login','User logged in via admin guard','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-17 11:49:39','2026-08-17 06:19:39','2026-08-17 06:19:39');
/*!40000 ALTER TABLE `activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `calendar_notes`
--

DROP TABLE IF EXISTS `calendar_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calendar_notes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `note` text NOT NULL,
  `is_private` tinyint(1) NOT NULL DEFAULT 1,
  `role` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calendar_notes`
--

LOCK TABLES `calendar_notes` WRITE;
/*!40000 ALTER TABLE `calendar_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `calendar_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `check_in_outs`
--

DROP TABLE IF EXISTS `check_in_outs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `check_in_outs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `check_in` timestamp NULL DEFAULT NULL,
  `check_out` timestamp NULL DEFAULT NULL,
  `check_in_ip` varchar(45) DEFAULT NULL,
  `check_out_ip` varchar(45) DEFAULT NULL,
  `check_in_notes` text DEFAULT NULL,
  `check_out_notes` text DEFAULT NULL,
  `total_minutes` int(11) DEFAULT NULL COMMENT 'Total minutes worked',
  `edited_by` int(11) DEFAULT NULL COMMENT 'Admin who edited the record',
  `edited_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `check_in_outs_member_id_foreign` (`member_id`),
  CONSTRAINT `check_in_outs_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `check_in_outs`
--

LOCK TABLES `check_in_outs` WRITE;
/*!40000 ALTER TABLE `check_in_outs` DISABLE KEYS */;
/*!40000 ALTER TABLE `check_in_outs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_activity_logs`
--

DROP TABLE IF EXISTS `construction_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `actor_type` varchar(255) DEFAULT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `module` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `reference_type` varchar(255) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `meta` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`meta`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `construction_activity_logs_company_id_foreign` (`company_id`),
  KEY `construction_activity_logs_project_id_foreign` (`project_id`),
  KEY `cal_actor_idx` (`actor_type`,`actor_id`),
  KEY `cal_reference_idx` (`reference_type`,`reference_id`),
  CONSTRAINT `construction_activity_logs_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `construction_companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_activity_logs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_activity_logs`
--

LOCK TABLES `construction_activity_logs` WRITE;
/*!40000 ALTER TABLE `construction_activity_logs` DISABLE KEYS */;
INSERT INTO `construction_activity_logs` VALUES (1,1,NULL,'App\\Models\\SuperAdmin',1,'company','created','App\\Models\\Construction\\Company',1,'{\"name\":\"dev pvt. ltd.\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-30 03:05:23'),(2,1,NULL,'App\\Models\\SuperAdmin',1,'client','created','App\\Models\\Construction\\Client',1,'{\"name\":\"dev\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-30 03:06:17'),(3,1,1,'App\\Models\\SuperAdmin',1,'project','created','App\\Models\\Construction\\Project',1,'{\"project_code\":\"PRJ-00001\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-30 03:07:36'),(4,1,1,'App\\Models\\SuperAdmin',1,'project_team','assigned','App\\Models\\Construction\\ProjectTeamMember',1,'{\"member_id\":\"5\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-30 03:12:31'),(5,1,1,'App\\Models\\SuperAdmin',1,'project_budget','approved','App\\Models\\Construction\\ProjectBudget',1,'{\"version_no\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-30 03:15:59'),(6,1,1,'App\\Models\\SuperAdmin',1,'survey_plan','created','App\\Models\\Construction\\SurveyPlan',1,'{\"assigned_members\":[]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-30 04:56:13'),(7,2,NULL,'App\\Models\\SuperAdmin',1,'company','created','App\\Models\\Construction\\Company',2,'{\"name\":\"cadmax\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-31 05:34:14'),(8,2,NULL,'App\\Models\\SuperAdmin',1,'client','created','App\\Models\\Construction\\Client',2,'{\"name\":\"abhinav\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-31 05:35:53'),(9,2,2,'App\\Models\\SuperAdmin',1,'project','created','App\\Models\\Construction\\Project',2,'{\"project_code\":\"PRJ-00002\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-31 05:38:22'),(10,2,2,'App\\Models\\SuperAdmin',1,'project_budget','approved','App\\Models\\Construction\\ProjectBudget',2,'{\"version_no\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-31 05:39:15'),(11,2,2,'App\\Models\\SuperAdmin',1,'project_team','assigned','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-31 05:40:28'),(12,2,2,'App\\Models\\SuperAdmin',1,'survey_plan','created','App\\Models\\Construction\\SurveyPlan',2,'{\"assigned_members\":[\"5\"]}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-31 05:43:08'),(13,2,2,'App\\Models\\SuperAdmin',1,'project_team','assigned','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-31 05:47:10'),(14,2,2,'App\\Models\\SuperAdmin',1,'project_team','assigned','App\\Models\\Construction\\ProjectTeamMember',3,'{\"member_id\":\"4\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-05 00:08:28'),(15,2,2,'App\\Models\\SuperAdmin',1,'project_team','assigned','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 00:53:44'),(16,2,2,'App\\Models\\SuperAdmin',1,'project_team','deactivated','App\\Models\\Construction\\ProjectTeamMember',3,'{\"member_id\":4,\"status\":\"inactive\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 02:33:10'),(17,2,2,'App\\Models\\SuperAdmin',1,'project_team','activated','App\\Models\\Construction\\ProjectTeamMember',3,'{\"member_id\":4,\"status\":\"active\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 02:33:10'),(18,2,2,'App\\Models\\SuperAdmin',1,'project_team','deactivated','App\\Models\\Construction\\ProjectTeamMember',3,'{\"member_id\":4,\"status\":\"inactive\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 02:33:11'),(19,2,2,'App\\Models\\SuperAdmin',1,'project_team','deactivated','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5,\"status\":\"inactive\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 02:33:19'),(20,2,2,'App\\Models\\SuperAdmin',1,'project_team','activated','App\\Models\\Construction\\ProjectTeamMember',3,'{\"member_id\":4,\"status\":\"active\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 02:33:25'),(21,2,2,'App\\Models\\SuperAdmin',1,'project_team','updated','App\\Models\\Construction\\ProjectTeamMember',3,'{\"member_id\":4}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 02:41:08'),(22,2,2,'App\\Models\\SuperAdmin',1,'project_team','updated','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 04:46:49'),(23,2,2,'App\\Models\\SuperAdmin',1,'project_team','removed',NULL,NULL,'{\"member_id\":4,\"member_name\":\"Pradeep Saini\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 05:05:22'),(24,2,2,'App\\Models\\SuperAdmin',1,'project_team','assigned','App\\Models\\Construction\\ProjectTeamMember',4,'{\"member_id\":\"6\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 05:12:39'),(25,2,2,'App\\Models\\SuperAdmin',1,'project_team','updated','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 05:44:28'),(26,2,2,'App\\Models\\SuperAdmin',1,'project_team','updated','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 05:45:13'),(27,2,2,'App\\Models\\SuperAdmin',1,'project_team','updated','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 05:49:49'),(28,2,2,'App\\Models\\SuperAdmin',1,'project_team','activated','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5,\"status\":\"active\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 05:52:12'),(29,2,2,'App\\Models\\SuperAdmin',1,'project_team','updated','App\\Models\\Construction\\ProjectTeamMember',2,'{\"member_id\":5}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-07 05:55:28'),(30,2,2,'App\\Models\\SuperAdmin',1,'project_budget','approved','App\\Models\\Construction\\ProjectBudget',3,'{\"version_no\":2}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-12 05:34:24'),(31,2,NULL,'App\\Models\\SuperAdmin',1,'company','updated','App\\Models\\Construction\\Company',2,'{\"name\":\"cadmax\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-12 06:07:33'),(32,2,NULL,'App\\Models\\SuperAdmin',1,'company','updated','App\\Models\\Construction\\Company',2,'{\"name\":\"cadmax\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-12 06:08:06'),(33,2,2,'App\\Models\\SuperAdmin',1,'billing_invoice','created','App\\Models\\Construction\\ClientInvoice',1,'{\"invoice_code\":\"INV-00001\",\"tax_type\":\"inter\",\"total_amount\":11.8}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-12 06:51:58'),(34,2,2,'App\\Models\\SuperAdmin',1,'billing_payment','received','App\\Models\\Construction\\ClientPayment',1,'{\"invoice_id\":1,\"payment_code\":\"PAY-00001\",\"amount\":11.8}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-12 06:53:22'),(35,2,2,'App\\Models\\SuperAdmin',1,'execution_plan','created','App\\Models\\Construction\\ExecutionPlan',1,'[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-12 06:54:38'),(36,1,NULL,'App\\Models\\SuperAdmin',1,'company','updated','App\\Models\\Construction\\Company',1,'{\"name\":\"goscopify\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-12 07:09:55'),(37,NULL,2,'App\\Models\\SuperAdmin',1,'execution_task_assignment','assigned','App\\Models\\Construction\\ExecutionTaskAssignee',1,'[]','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-13 00:24:58'),(38,2,2,'App\\Models\\SuperAdmin',1,'execution_task','created','App\\Models\\Construction\\ExecutionTask',1,'{\"assignee_count\":1}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-08-13 00:24:58');
/*!40000 ALTER TABLE `construction_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_attendance_records`
--

DROP TABLE IF EXISTS `construction_attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_attendance_records` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `execution_task_id` bigint(20) unsigned DEFAULT NULL,
  `member_id` bigint(20) unsigned NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_at` timestamp NULL DEFAULT NULL,
  `check_out_at` timestamp NULL DEFAULT NULL,
  `check_in_latitude` decimal(10,7) DEFAULT NULL,
  `check_in_longitude` decimal(10,7) DEFAULT NULL,
  `check_out_latitude` decimal(10,7) DEFAULT NULL,
  `check_out_longitude` decimal(10,7) DEFAULT NULL,
  `gps_accuracy_meters` decimal(10,2) DEFAULT NULL,
  `attendance_type` varchar(255) NOT NULL DEFAULT 'present',
  `notes` text DEFAULT NULL,
  `reviewed_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_attendance_daily_unique` (`project_id`,`member_id`,`attendance_date`),
  KEY `construction_attendance_records_execution_task_id_foreign` (`execution_task_id`),
  KEY `construction_attendance_records_member_id_foreign` (`member_id`),
  KEY `car_reviewed_by_fk` (`reviewed_by_member_id`),
  KEY `construction_attendance_records_project_id_attendance_date_index` (`project_id`,`attendance_date`),
  KEY `construction_attendance_records_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `car_reviewed_by_fk` FOREIGN KEY (`reviewed_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_attendance_records_execution_task_id_foreign` FOREIGN KEY (`execution_task_id`) REFERENCES `construction_execution_tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_attendance_records_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_attendance_records_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_attendance_records`
--

LOCK TABLES `construction_attendance_records` WRITE;
/*!40000 ALTER TABLE `construction_attendance_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_attendance_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_client_invoice_items`
--

DROP TABLE IF EXISTS `construction_client_invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_client_invoice_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL DEFAULT 1.00,
  `unit` varchar(30) DEFAULT NULL,
  `rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_subtotal` decimal(14,2) NOT NULL DEFAULT 0.00,
  `gst_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total_tax` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ccii_invoice_fk` (`invoice_id`),
  CONSTRAINT `ccii_invoice_fk` FOREIGN KEY (`invoice_id`) REFERENCES `construction_client_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_client_invoice_items`
--

LOCK TABLES `construction_client_invoice_items` WRITE;
/*!40000 ALTER TABLE `construction_client_invoice_items` DISABLE KEYS */;
INSERT INTO `construction_client_invoice_items` VALUES (1,1,'12000',1.00,'15',10.00,10.00,18.00,0.00,0.00,1.80,1.80,11.80,'2026-08-12 06:51:58','2026-08-12 06:51:58');
/*!40000 ALTER TABLE `construction_client_invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_client_invoices`
--

DROP TABLE IF EXISTS `construction_client_invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_client_invoices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `invoice_code` varchar(30) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `tax_type` varchar(20) NOT NULL DEFAULT 'intra',
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `notes` text DEFAULT NULL,
  `subtotal_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_client_invoices_invoice_code_unique` (`invoice_code`),
  KEY `cci_created_by_idx` (`created_by_type`,`created_by_id`),
  KEY `cci_project_status_idx` (`project_id`,`status`),
  CONSTRAINT `construction_client_invoices_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_client_invoices`
--

LOCK TABLES `construction_client_invoices` WRITE;
/*!40000 ALTER TABLE `construction_client_invoices` DISABLE KEYS */;
INSERT INTO `construction_client_invoices` VALUES (1,2,'INV-00001','2026-08-12','2026-08-20','inter','paid','jjjjjj',10.00,0.00,0.00,1.80,1.80,11.80,11.80,0.00,'App\\Models\\SuperAdmin',1,'2026-08-12 06:51:58','2026-08-12 06:53:22',NULL);
/*!40000 ALTER TABLE `construction_client_invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_client_payments`
--

DROP TABLE IF EXISTS `construction_client_payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_client_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `invoice_id` bigint(20) unsigned NOT NULL,
  `payment_code` varchar(30) NOT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `amount` decimal(14,2) NOT NULL,
  `method` varchar(30) NOT NULL DEFAULT 'bank_transfer',
  `reference_no` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by_type` varchar(255) DEFAULT NULL,
  `received_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_client_payments_payment_code_unique` (`payment_code`),
  KEY `ccp_received_by_idx` (`received_by_type`,`received_by_id`),
  KEY `ccp_invoice_fk` (`invoice_id`),
  KEY `ccp_project_invoice_idx` (`project_id`,`invoice_id`),
  CONSTRAINT `ccp_invoice_fk` FOREIGN KEY (`invoice_id`) REFERENCES `construction_client_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_client_payments_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_client_payments`
--

LOCK TABLES `construction_client_payments` WRITE;
/*!40000 ALTER TABLE `construction_client_payments` DISABLE KEYS */;
INSERT INTO `construction_client_payments` VALUES (1,2,1,'PAY-00001','2026-08-12 12:22:00',11.80,'upi','22334455','wkjswkqjsw','App\\Models\\SuperAdmin',1,'2026-08-12 06:53:22','2026-08-12 06:53:22');
/*!40000 ALTER TABLE `construction_client_payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_clients`
--

DROP TABLE IF EXISTS `construction_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_clients` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `client_code` varchar(255) NOT NULL,
  `client_type` varchar(255) NOT NULL DEFAULT 'individual',
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `alternate_phone` varchar(255) DEFAULT NULL,
  `gst_number` varchar(255) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `site_address` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_clients_client_code_unique` (`client_code`),
  KEY `construction_clients_company_id_foreign` (`company_id`),
  KEY `ccli_created_by_idx` (`created_by_type`,`created_by_id`),
  CONSTRAINT `construction_clients_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `construction_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_clients`
--

LOCK TABLES `construction_clients` WRITE;
/*!40000 ALTER TABLE `construction_clients` DISABLE KEYS */;
INSERT INTO `construction_clients` VALUES (1,1,'CLI-00001','company','dev','9900990010',NULL,NULL,NULL,NULL,NULL,NULL,NULL,'active','App\\Models\\SuperAdmin',1,'2026-07-30 03:06:17','2026-07-30 03:06:17',NULL),(2,2,'CLI-00002','government','abhinav','abhinav','abhinav@gmail.com','89009876',NULL,'56789034567','105 A murlipura','murlipura','nothing','active','App\\Models\\SuperAdmin',1,'2026-07-31 05:35:53','2026-07-31 05:35:53',NULL);
/*!40000 ALTER TABLE `construction_clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_companies`
--

DROP TABLE IF EXISTS `construction_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_companies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `legal_name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `gst_number` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings`)),
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cc_created_by_idx` (`created_by_type`,`created_by_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_companies`
--

LOCK TABLES `construction_companies` WRITE;
/*!40000 ALTER TABLE `construction_companies` DISABLE KEYS */;
INSERT INTO `construction_companies` VALUES (1,'goscopify','goscopify','goscopify@info.com','92973776364','jknjkw8788998','105 A murlipura',NULL,NULL,'active','App\\Models\\SuperAdmin',1,'2026-07-30 03:05:23','2026-08-12 07:09:55',NULL),(2,'cadmax','cadmax','cadmax@gmail.com','8989898989','123456789','77 soni ka bag murlipura jaipur',NULL,NULL,'active','App\\Models\\SuperAdmin',1,'2026-07-31 05:34:14','2026-08-12 06:08:06',NULL);
/*!40000 ALTER TABLE `construction_companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_daily_progress_items`
--

DROP TABLE IF EXISTS `construction_daily_progress_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_daily_progress_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `daily_progress_report_id` bigint(20) unsigned NOT NULL,
  `execution_task_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(255) DEFAULT NULL,
  `planned_quantity` decimal(12,2) DEFAULT NULL,
  `completed_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `percent_complete` decimal(5,2) NOT NULL DEFAULT 0.00,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_daily_progress_items_project_id_foreign` (`project_id`),
  KEY `construction_daily_progress_items_execution_task_id_foreign` (`execution_task_id`),
  KEY `cdpi_report_fk` (`daily_progress_report_id`),
  CONSTRAINT `cdpi_report_fk` FOREIGN KEY (`daily_progress_report_id`) REFERENCES `construction_daily_progress_reports` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_daily_progress_items_execution_task_id_foreign` FOREIGN KEY (`execution_task_id`) REFERENCES `construction_execution_tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_daily_progress_items_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_daily_progress_items`
--

LOCK TABLES `construction_daily_progress_items` WRITE;
/*!40000 ALTER TABLE `construction_daily_progress_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_daily_progress_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_daily_progress_reports`
--

DROP TABLE IF EXISTS `construction_daily_progress_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_daily_progress_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `execution_task_id` bigint(20) unsigned DEFAULT NULL,
  `report_date` date NOT NULL,
  `submitted_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `work_completed` text DEFAULT NULL,
  `blockers` text DEFAULT NULL,
  `workforce_count` int(10) unsigned NOT NULL DEFAULT 0,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `gps_accuracy_meters` decimal(10,2) DEFAULT NULL,
  `weather_summary` varchar(255) DEFAULT NULL,
  `supporting_document_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_daily_progress_reports_execution_task_id_foreign` (`execution_task_id`),
  KEY `cdpr_submitted_by_fk` (`submitted_by_member_id`),
  KEY `cdpr_reviewed_by_fk` (`reviewed_by_member_id`),
  KEY `construction_daily_progress_reports_project_id_report_date_index` (`project_id`,`report_date`),
  KEY `construction_daily_progress_reports_project_id_status_index` (`project_id`,`status`),
  KEY `cdpr_support_doc_fk` (`supporting_document_id`),
  CONSTRAINT `cdpr_reviewed_by_fk` FOREIGN KEY (`reviewed_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cdpr_submitted_by_fk` FOREIGN KEY (`submitted_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cdpr_support_doc_fk` FOREIGN KEY (`supporting_document_id`) REFERENCES `construction_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_daily_progress_reports_execution_task_id_foreign` FOREIGN KEY (`execution_task_id`) REFERENCES `construction_execution_tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_daily_progress_reports_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_daily_progress_reports`
--

LOCK TABLES `construction_daily_progress_reports` WRITE;
/*!40000 ALTER TABLE `construction_daily_progress_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_daily_progress_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_documents`
--

DROP TABLE IF EXISTS `construction_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `documentable_type` varchar(255) NOT NULL,
  `documentable_id` bigint(20) unsigned NOT NULL,
  `folder` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) unsigned NOT NULL DEFAULT 0,
  `disk` varchar(255) NOT NULL DEFAULT 'public',
  `path` varchar(255) NOT NULL,
  `version_no` int(10) unsigned NOT NULL DEFAULT 1,
  `uploaded_by_type` varchar(255) DEFAULT NULL,
  `uploaded_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_documents_company_id_foreign` (`company_id`),
  KEY `construction_documents_project_id_foreign` (`project_id`),
  KEY `cdoc_documentable_idx` (`documentable_type`,`documentable_id`),
  KEY `cdoc_uploaded_by_idx` (`uploaded_by_type`,`uploaded_by_id`),
  CONSTRAINT `construction_documents_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `construction_companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_documents_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_documents`
--

LOCK TABLES `construction_documents` WRITE;
/*!40000 ALTER TABLE `construction_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_drafting_jobs`
--

DROP TABLE IF EXISTS `construction_drafting_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_drafting_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `survey_submission_id` bigint(20) unsigned NOT NULL,
  `assigned_to_member_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_by_type` varchar(255) DEFAULT NULL,
  `assigned_by_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'queued',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_drafting_jobs_project_id_foreign` (`project_id`),
  KEY `construction_drafting_jobs_survey_submission_id_foreign` (`survey_submission_id`),
  KEY `construction_drafting_jobs_assigned_to_member_id_foreign` (`assigned_to_member_id`),
  KEY `cdj_assigned_by_idx` (`assigned_by_type`,`assigned_by_id`),
  CONSTRAINT `construction_drafting_jobs_assigned_to_member_id_foreign` FOREIGN KEY (`assigned_to_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_drafting_jobs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_drafting_jobs_survey_submission_id_foreign` FOREIGN KEY (`survey_submission_id`) REFERENCES `construction_survey_submissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_drafting_jobs`
--

LOCK TABLES `construction_drafting_jobs` WRITE;
/*!40000 ALTER TABLE `construction_drafting_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_drafting_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_drawing_approvals`
--

DROP TABLE IF EXISTS `construction_drawing_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_drawing_approvals` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `drawing_revision_id` bigint(20) unsigned NOT NULL,
  `requested_by_type` varchar(255) DEFAULT NULL,
  `requested_by_id` bigint(20) unsigned DEFAULT NULL,
  `requested_at` timestamp NULL DEFAULT NULL,
  `approved_by_type` varchar(255) DEFAULT NULL,
  `approved_by_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `decision` varchar(255) NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_drawing_approvals_project_id_foreign` (`project_id`),
  KEY `construction_drawing_approvals_drawing_revision_id_foreign` (`drawing_revision_id`),
  KEY `cda_requested_by_idx` (`requested_by_type`,`requested_by_id`),
  KEY `cda_approved_by_idx` (`approved_by_type`,`approved_by_id`),
  CONSTRAINT `construction_drawing_approvals_drawing_revision_id_foreign` FOREIGN KEY (`drawing_revision_id`) REFERENCES `construction_drawing_revisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_drawing_approvals_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_drawing_approvals`
--

LOCK TABLES `construction_drawing_approvals` WRITE;
/*!40000 ALTER TABLE `construction_drawing_approvals` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_drawing_approvals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_drawing_revisions`
--

DROP TABLE IF EXISTS `construction_drawing_revisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_drawing_revisions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `drafting_job_id` bigint(20) unsigned NOT NULL,
  `revision_no` int(10) unsigned NOT NULL DEFAULT 1,
  `dwg_document_id` bigint(20) unsigned DEFAULT NULL,
  `pdf_document_id` bigint(20) unsigned DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `uploaded_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_drawing_revisions_project_id_foreign` (`project_id`),
  KEY `construction_drawing_revisions_drafting_job_id_foreign` (`drafting_job_id`),
  KEY `construction_drawing_revisions_dwg_document_id_foreign` (`dwg_document_id`),
  KEY `construction_drawing_revisions_pdf_document_id_foreign` (`pdf_document_id`),
  KEY `construction_drawing_revisions_uploaded_by_member_id_foreign` (`uploaded_by_member_id`),
  CONSTRAINT `construction_drawing_revisions_drafting_job_id_foreign` FOREIGN KEY (`drafting_job_id`) REFERENCES `construction_drafting_jobs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_drawing_revisions_dwg_document_id_foreign` FOREIGN KEY (`dwg_document_id`) REFERENCES `construction_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_drawing_revisions_pdf_document_id_foreign` FOREIGN KEY (`pdf_document_id`) REFERENCES `construction_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_drawing_revisions_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_drawing_revisions_uploaded_by_member_id_foreign` FOREIGN KEY (`uploaded_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_drawing_revisions`
--

LOCK TABLES `construction_drawing_revisions` WRITE;
/*!40000 ALTER TABLE `construction_drawing_revisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_drawing_revisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_equipment_allocations`
--

DROP TABLE IF EXISTS `construction_equipment_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_equipment_allocations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `equipment_id` bigint(20) unsigned NOT NULL,
  `assigned_to_member_id` bigint(20) unsigned DEFAULT NULL,
  `allocated_at` timestamp NULL DEFAULT NULL,
  `allocate_latitude` decimal(10,7) DEFAULT NULL,
  `allocate_longitude` decimal(10,7) DEFAULT NULL,
  `allocate_gps_accuracy_meters` decimal(10,2) DEFAULT NULL,
  `allocate_gps_verified` tinyint(1) NOT NULL DEFAULT 0,
  `returned_at` timestamp NULL DEFAULT NULL,
  `return_latitude` decimal(10,7) DEFAULT NULL,
  `return_longitude` decimal(10,7) DEFAULT NULL,
  `return_gps_accuracy_meters` decimal(10,2) DEFAULT NULL,
  `return_gps_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `allocated_by_type` varchar(255) DEFAULT NULL,
  `allocated_by_id` bigint(20) unsigned DEFAULT NULL,
  `returned_by_type` varchar(255) DEFAULT NULL,
  `returned_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ceqa_allocated_by_idx` (`allocated_by_type`,`allocated_by_id`),
  KEY `ceqa_returned_by_idx` (`returned_by_type`,`returned_by_id`),
  KEY `ceqa_equipment_fk` (`equipment_id`),
  KEY `ceqa_assigned_to_fk` (`assigned_to_member_id`),
  KEY `ceqa_project_equipment_status_idx` (`project_id`,`equipment_id`,`status`),
  KEY `ceqa_project_assignee_idx` (`project_id`,`assigned_to_member_id`),
  CONSTRAINT `ceqa_assigned_to_fk` FOREIGN KEY (`assigned_to_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ceqa_equipment_fk` FOREIGN KEY (`equipment_id`) REFERENCES `construction_equipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_equipment_allocations_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_equipment_allocations`
--

LOCK TABLES `construction_equipment_allocations` WRITE;
/*!40000 ALTER TABLE `construction_equipment_allocations` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_equipment_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_equipment_usage_logs`
--

DROP TABLE IF EXISTS `construction_equipment_usage_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_equipment_usage_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `equipment_id` bigint(20) unsigned NOT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `log_date` date NOT NULL,
  `hours_used` decimal(10,2) NOT NULL DEFAULT 0.00,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `gps_accuracy_meters` decimal(10,2) DEFAULT NULL,
  `gps_verified` tinyint(1) NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cequl_equipment_fk` (`equipment_id`),
  KEY `cequl_member_fk` (`member_id`),
  KEY `cequl_project_equipment_date_idx` (`project_id`,`equipment_id`,`log_date`),
  CONSTRAINT `cequl_equipment_fk` FOREIGN KEY (`equipment_id`) REFERENCES `construction_equipments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cequl_member_fk` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_equipment_usage_logs_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_equipment_usage_logs`
--

LOCK TABLES `construction_equipment_usage_logs` WRITE;
/*!40000 ALTER TABLE `construction_equipment_usage_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_equipment_usage_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_equipments`
--

DROP TABLE IF EXISTS `construction_equipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_equipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `equipment_code` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `equipment_type` varchar(80) DEFAULT NULL,
  `serial_number` varchar(60) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_equipments_equipment_code_unique` (`equipment_code`),
  KEY `ceq_created_by_idx` (`created_by_type`,`created_by_id`),
  KEY `ceq_project_status_idx` (`project_id`,`status`),
  KEY `ceq_project_type_idx` (`project_id`,`equipment_type`),
  CONSTRAINT `construction_equipments_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_equipments`
--

LOCK TABLES `construction_equipments` WRITE;
/*!40000 ALTER TABLE `construction_equipments` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_equipments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_execution_plans`
--

DROP TABLE IF EXISTS `construction_execution_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_execution_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `plan_code` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `planned_progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `actual_progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `approved_by_type` varchar(255) DEFAULT NULL,
  `approved_by_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'planned',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_execution_plans_plan_code_unique` (`plan_code`),
  KEY `construction_execution_plans_project_id_foreign` (`project_id`),
  KEY `cep_created_by_idx` (`created_by_type`,`created_by_id`),
  KEY `cep_approved_by_idx` (`approved_by_type`,`approved_by_id`),
  CONSTRAINT `construction_execution_plans_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_execution_plans`
--

LOCK TABLES `construction_execution_plans` WRITE;
/*!40000 ALTER TABLE `construction_execution_plans` DISABLE KEYS */;
INSERT INTO `construction_execution_plans` VALUES (1,2,'EXP-00001','ekokkeoi','ekj2oi3jeo3e','2026-08-29','2026-09-03',0.00,0.00,'App\\Models\\SuperAdmin',1,NULL,NULL,NULL,'planned','2026-08-12 06:54:38','2026-08-12 06:54:38');
/*!40000 ALTER TABLE `construction_execution_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_execution_task_assignees`
--

DROP TABLE IF EXISTS `construction_execution_task_assignees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_execution_task_assignees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `execution_task_id` bigint(20) unsigned NOT NULL,
  `member_id` bigint(20) unsigned NOT NULL,
  `assignment_role` varchar(255) NOT NULL DEFAULT 'worker',
  `assigned_from` date DEFAULT NULL,
  `assigned_to` date DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `assigned_by_type` varchar(255) DEFAULT NULL,
  `assigned_by_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_execution_task_member_unique` (`execution_task_id`,`member_id`),
  KEY `construction_execution_task_assignees_project_id_foreign` (`project_id`),
  KEY `construction_execution_task_assignees_member_id_foreign` (`member_id`),
  KEY `ceta_assigned_by_idx` (`assigned_by_type`,`assigned_by_id`),
  CONSTRAINT `construction_execution_task_assignees_execution_task_id_foreign` FOREIGN KEY (`execution_task_id`) REFERENCES `construction_execution_tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_execution_task_assignees_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_execution_task_assignees_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_execution_task_assignees`
--

LOCK TABLES `construction_execution_task_assignees` WRITE;
/*!40000 ALTER TABLE `construction_execution_task_assignees` DISABLE KEYS */;
INSERT INTO `construction_execution_task_assignees` VALUES (1,2,1,5,'worker','2026-08-20','2026-08-28',1,'App\\Models\\SuperAdmin',1,'active','2026-08-13 00:24:58','2026-08-13 00:24:58');
/*!40000 ALTER TABLE `construction_execution_task_assignees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_execution_tasks`
--

DROP TABLE IF EXISTS `construction_execution_tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_execution_tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `execution_plan_id` bigint(20) unsigned NOT NULL,
  `parent_task_id` bigint(20) unsigned DEFAULT NULL,
  `task_code` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `planned_start_date` date DEFAULT NULL,
  `planned_end_date` date DEFAULT NULL,
  `actual_start_date` date DEFAULT NULL,
  `actual_end_date` date DEFAULT NULL,
  `priority` varchar(255) NOT NULL DEFAULT 'medium',
  `planned_quantity` decimal(12,2) DEFAULT NULL,
  `completed_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `unit` varchar(255) DEFAULT NULL,
  `progress_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `requires_daily_update` tinyint(1) NOT NULL DEFAULT 1,
  `requires_gps_verification` tinyint(1) NOT NULL DEFAULT 1,
  `supervisor_member_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'planned',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_execution_tasks_task_code_unique` (`task_code`),
  KEY `construction_execution_tasks_parent_task_id_foreign` (`parent_task_id`),
  KEY `construction_execution_tasks_supervisor_member_id_foreign` (`supervisor_member_id`),
  KEY `construction_execution_tasks_project_id_status_index` (`project_id`,`status`),
  KEY `construction_execution_tasks_execution_plan_id_status_index` (`execution_plan_id`,`status`),
  CONSTRAINT `construction_execution_tasks_execution_plan_id_foreign` FOREIGN KEY (`execution_plan_id`) REFERENCES `construction_execution_plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_execution_tasks_parent_task_id_foreign` FOREIGN KEY (`parent_task_id`) REFERENCES `construction_execution_tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_execution_tasks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_execution_tasks_supervisor_member_id_foreign` FOREIGN KEY (`supervisor_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_execution_tasks`
--

LOCK TABLES `construction_execution_tasks` WRITE;
/*!40000 ALTER TABLE `construction_execution_tasks` DISABLE KEYS */;
INSERT INTO `construction_execution_tasks` VALUES (1,2,1,NULL,'EXT-00001','isdhiah','ahxbhBA','2026-08-20','2026-08-28',NULL,NULL,'high',61.00,0.00,'71',0.00,1,1,5,'planned','2026-08-13 00:24:58','2026-08-13 00:24:58');
/*!40000 ALTER TABLE `construction_execution_tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_material_issue_items`
--

DROP TABLE IF EXISTS `construction_material_issue_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_material_issue_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_issue_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `execution_task_id` bigint(20) unsigned DEFAULT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cmii_issue_fk` (`material_issue_id`),
  KEY `cmii_material_fk` (`material_id`),
  KEY `cmii_task_fk` (`execution_task_id`),
  CONSTRAINT `cmii_issue_fk` FOREIGN KEY (`material_issue_id`) REFERENCES `construction_material_issues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cmii_material_fk` FOREIGN KEY (`material_id`) REFERENCES `construction_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cmii_task_fk` FOREIGN KEY (`execution_task_id`) REFERENCES `construction_execution_tasks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_material_issue_items`
--

LOCK TABLES `construction_material_issue_items` WRITE;
/*!40000 ALTER TABLE `construction_material_issue_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_material_issue_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_material_issues`
--

DROP TABLE IF EXISTS `construction_material_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_material_issues` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `issue_code` varchar(30) NOT NULL,
  `issued_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `issue_date` date NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `gps_accuracy_meters` decimal(10,2) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'issued',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_material_issues_issue_code_unique` (`issue_code`),
  KEY `cmi_issued_by_fk` (`issued_by_member_id`),
  KEY `construction_material_issues_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `cmi_issued_by_fk` FOREIGN KEY (`issued_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_material_issues_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_material_issues`
--

LOCK TABLES `construction_material_issues` WRITE;
/*!40000 ALTER TABLE `construction_material_issues` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_material_issues` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_material_receipt_items`
--

DROP TABLE IF EXISTS `construction_material_receipt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_material_receipt_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `material_receipt_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cmri_receipt_fk` (`material_receipt_id`),
  KEY `cmri_material_fk` (`material_id`),
  CONSTRAINT `cmri_material_fk` FOREIGN KEY (`material_id`) REFERENCES `construction_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cmri_receipt_fk` FOREIGN KEY (`material_receipt_id`) REFERENCES `construction_material_receipts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_material_receipt_items`
--

LOCK TABLES `construction_material_receipt_items` WRITE;
/*!40000 ALTER TABLE `construction_material_receipt_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_material_receipt_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_material_receipts`
--

DROP TABLE IF EXISTS `construction_material_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_material_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `purchase_order_id` bigint(20) unsigned DEFAULT NULL,
  `receipt_code` varchar(30) NOT NULL,
  `received_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `received_at` timestamp NULL DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `gps_accuracy_meters` decimal(10,2) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'received',
  `notes` text DEFAULT NULL,
  `receipt_document_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_material_receipts_receipt_code_unique` (`receipt_code`),
  KEY `cmr_po_fk` (`purchase_order_id`),
  KEY `cmr_received_by_fk` (`received_by_member_id`),
  KEY `construction_material_receipts_project_id_status_index` (`project_id`,`status`),
  KEY `cmr_receipt_doc_fk` (`receipt_document_id`),
  CONSTRAINT `cmr_po_fk` FOREIGN KEY (`purchase_order_id`) REFERENCES `construction_purchase_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cmr_receipt_doc_fk` FOREIGN KEY (`receipt_document_id`) REFERENCES `construction_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cmr_received_by_fk` FOREIGN KEY (`received_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_material_receipts_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_material_receipts`
--

LOCK TABLES `construction_material_receipts` WRITE;
/*!40000 ALTER TABLE `construction_material_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_material_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_material_stocks`
--

DROP TABLE IF EXISTS `construction_material_stocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_material_stocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `on_hand_quantity` decimal(14,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cms_project_material_unique` (`project_id`,`material_id`),
  KEY `cms_material_fk` (`material_id`),
  KEY `construction_material_stocks_project_id_index` (`project_id`),
  CONSTRAINT `cms_material_fk` FOREIGN KEY (`material_id`) REFERENCES `construction_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_material_stocks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_material_stocks`
--

LOCK TABLES `construction_material_stocks` WRITE;
/*!40000 ALTER TABLE `construction_material_stocks` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_material_stocks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_materials`
--

DROP TABLE IF EXISTS `construction_materials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_materials` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `material_code` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `unit` varchar(50) NOT NULL DEFAULT 'nos',
  `default_rate` decimal(12,2) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_materials_material_code_unique` (`material_code`),
  KEY `construction_materials_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `construction_materials_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_materials`
--

LOCK TABLES `construction_materials` WRITE;
/*!40000 ALTER TABLE `construction_materials` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_materials` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_member_role_assignments`
--

DROP TABLE IF EXISTS `construction_member_role_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_member_role_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `project_id` bigint(20) unsigned DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_member_role_unique` (`member_id`,`role_id`,`project_id`),
  KEY `construction_member_role_assignments_role_id_foreign` (`role_id`),
  KEY `construction_member_role_assignments_project_id_foreign` (`project_id`),
  CONSTRAINT `construction_member_role_assignments_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_member_role_assignments_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_member_role_assignments_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `construction_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_member_role_assignments`
--

LOCK TABLES `construction_member_role_assignments` WRITE;
/*!40000 ALTER TABLE `construction_member_role_assignments` DISABLE KEYS */;
INSERT INTO `construction_member_role_assignments` VALUES (1,4,6,NULL,1,'2026-07-21 03:32:33','2026-07-21 03:32:33'),(2,5,6,NULL,1,'2026-07-22 02:46:29','2026-07-22 02:46:29'),(3,5,6,1,1,'2026-07-30 03:12:31','2026-07-30 03:12:31'),(4,5,3,2,1,'2026-07-31 05:40:28','2026-07-31 05:40:28'),(5,5,4,2,1,'2026-07-31 05:47:10','2026-07-31 05:47:10'),(6,6,6,NULL,1,'2026-07-31 06:48:24','2026-07-31 06:48:24'),(7,4,4,2,1,'2026-08-05 00:08:28','2026-08-05 00:08:28'),(8,5,6,2,1,'2026-08-07 00:53:44','2026-08-07 00:53:44'),(9,4,5,2,1,'2026-08-07 02:41:08','2026-08-07 02:41:08'),(10,5,2,2,1,'2026-08-07 04:46:49','2026-08-07 04:46:49'),(11,6,5,2,1,'2026-08-07 05:12:39','2026-08-07 05:12:39');
/*!40000 ALTER TABLE `construction_member_role_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_permissions`
--

DROP TABLE IF EXISTS `construction_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_permissions_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_permissions`
--

LOCK TABLES `construction_permissions` WRITE;
/*!40000 ALTER TABLE `construction_permissions` DISABLE KEYS */;
INSERT INTO `construction_permissions` VALUES (1,'Company Manage','company.manage','company',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(2,'Client Manage','client.manage','client',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(3,'Project Manage','project.manage','project',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(4,'Project Budget Approve','project_budget.approve','project_budget',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(5,'Project Team Manage','project_team.manage','project_team',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(6,'Survey Plan Manage','survey_plan.manage','survey_plan',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(7,'Survey Submission Review','survey_submission.review','survey_submission',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(8,'Drafting Manage','drafting.manage','drafting',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(9,'Drawing Approval Manage','drawing_approval.manage','drawing_approval',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(10,'Execution Manage','execution.manage','execution',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(11,'Execution Task Manage','execution_task.manage','execution_task',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(12,'Daily Progress Manage','dpr.manage','daily_progress',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(13,'Daily Progress Review','dpr.review','daily_progress',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(14,'Attendance Manage','attendance.manage','attendance',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(15,'Attendance Review','attendance.review','attendance',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(16,'Vendor Manage','vendor.manage','materials',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(17,'Material Manage','material.manage','materials',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(18,'Purchase Request Manage','purchase_request.manage','materials',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(19,'Purchase Order Manage','purchase_order.manage','materials',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(20,'Material Receipt Manage','material_receipt.manage','materials',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(21,'Material Issue Manage','material_issue.manage','materials',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(22,'Material Stock Manage','material_stock.manage','materials',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(23,'Vehicle Manage','vehicle.manage','vehicles',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(24,'Vehicle Assignment Manage','vehicle_assignment.manage','vehicles',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(25,'Vehicle Tracking Manage','vehicle_tracking.manage','vehicles',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(26,'Equipment Manage','equipment.manage','equipment',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(27,'Equipment Allocation Manage','equipment_allocation.manage','equipment',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(28,'Equipment Usage Manage','equipment_usage.manage','equipment',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(29,'Billing Invoice Manage','billing_invoice.manage','billing',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(30,'Billing Payment Manage','billing_payment.manage','billing',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(31,'Handover Manage','handover.manage','handover',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(32,'Project Closure Manage','project_closure.manage','handover',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(33,'Document Manage','document.manage','document',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(34,'Activity Log View','activity_log.view','activity_log',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(35,'Dashboard View','dashboard.view','dashboard',NULL,'2026-07-21 03:26:39','2026-07-21 03:26:39'),(36,'Survey View','survey.view','survey_plan',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(37,'Survey Create','survey.create','survey_plan',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(38,'Survey Submit','survey.submit','survey_plan',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(39,'Execution Task View','execution.task.view','execution_task',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(40,'Execution Task Update','execution.task.update','execution_task',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(41,'Daily Progress Create','dpr.create','daily_progress',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(42,'Daily Progress Submit','dpr.submit','daily_progress',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(43,'Attendance Mark','attendance.mark','attendance',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(44,'Vehicle Trip Start','vehicle.trip.start','vehicles',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(45,'Vehicle Trip End','vehicle.trip.end','vehicles',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40'),(46,'Vehicle Location Update','vehicle.location.update','vehicles',NULL,'2026-08-13 02:08:40','2026-08-13 02:08:40');
/*!40000 ALTER TABLE `construction_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_project_budgets`
--

DROP TABLE IF EXISTS `construction_project_budgets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_project_budgets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `version_no` int(10) unsigned NOT NULL DEFAULT 1,
  `estimated_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `approved_amount` decimal(15,2) DEFAULT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `notes` text DEFAULT NULL,
  `submitted_by_type` varchar(255) DEFAULT NULL,
  `submitted_by_id` bigint(20) unsigned DEFAULT NULL,
  `approved_by_type` varchar(255) DEFAULT NULL,
  `approved_by_id` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_project_budgets_project_id_foreign` (`project_id`),
  KEY `cpb_submitted_by_idx` (`submitted_by_type`,`submitted_by_id`),
  KEY `cpb_approved_by_idx` (`approved_by_type`,`approved_by_id`),
  CONSTRAINT `construction_project_budgets_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_project_budgets`
--

LOCK TABLES `construction_project_budgets` WRITE;
/*!40000 ALTER TABLE `construction_project_budgets` DISABLE KEYS */;
INSERT INTO `construction_project_budgets` VALUES (1,1,1,100000000.00,90000000.00,'INR',NULL,'App\\Models\\SuperAdmin',1,'App\\Models\\SuperAdmin',1,'2026-07-30 03:15:59','approved','2026-07-30 03:15:59','2026-07-30 03:15:59'),(2,2,1,150000000.00,180000000.00,'INR','nothing','App\\Models\\SuperAdmin',1,'App\\Models\\SuperAdmin',1,'2026-07-31 05:39:15','approved','2026-07-31 05:39:15','2026-07-31 05:39:15'),(3,2,2,10000.00,20000.00,'INR','nothing','App\\Models\\SuperAdmin',1,'App\\Models\\SuperAdmin',1,'2026-08-12 05:34:24','approved','2026-08-12 05:34:24','2026-08-12 05:34:24');
/*!40000 ALTER TABLE `construction_project_budgets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_project_handover_items`
--

DROP TABLE IF EXISTS `construction_project_handover_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_project_handover_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `handover_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(80) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cphi_handover_status_idx` (`handover_id`,`status`),
  CONSTRAINT `cphi_handover_fk` FOREIGN KEY (`handover_id`) REFERENCES `construction_project_handovers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_project_handover_items`
--

LOCK TABLES `construction_project_handover_items` WRITE;
/*!40000 ALTER TABLE `construction_project_handover_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_project_handover_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_project_handovers`
--

DROP TABLE IF EXISTS `construction_project_handovers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_project_handovers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `handover_code` varchar(30) NOT NULL,
  `planned_handover_date` date DEFAULT NULL,
  `actual_handover_at` timestamp NULL DEFAULT NULL,
  `closure_date` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `client_signatory_name` varchar(255) DEFAULT NULL,
  `client_signatory_role` varchar(255) DEFAULT NULL,
  `signoff_notes` text DEFAULT NULL,
  `final_document_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `handed_over_by_type` varchar(255) DEFAULT NULL,
  `handed_over_by_id` bigint(20) unsigned DEFAULT NULL,
  `closed_by_type` varchar(255) DEFAULT NULL,
  `closed_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_project_handovers_handover_code_unique` (`handover_code`),
  KEY `cph_created_by_idx` (`created_by_type`,`created_by_id`),
  KEY `cph_handover_by_idx` (`handed_over_by_type`,`handed_over_by_id`),
  KEY `cph_closed_by_idx` (`closed_by_type`,`closed_by_id`),
  KEY `cph_final_doc_fk` (`final_document_id`),
  KEY `cph_project_status_idx` (`project_id`,`status`),
  CONSTRAINT `construction_project_handovers_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cph_final_doc_fk` FOREIGN KEY (`final_document_id`) REFERENCES `construction_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_project_handovers`
--

LOCK TABLES `construction_project_handovers` WRITE;
/*!40000 ALTER TABLE `construction_project_handovers` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_project_handovers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_project_team_members`
--

DROP TABLE IF EXISTS `construction_project_team_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_project_team_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `member_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_from` date DEFAULT NULL,
  `assigned_to` date DEFAULT NULL,
  `assignment_scope` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `assigned_by_type` varchar(255) DEFAULT NULL,
  `assigned_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_project_team_role_unique` (`project_id`,`member_id`,`role_id`),
  KEY `construction_project_team_members_member_id_foreign` (`member_id`),
  KEY `construction_project_team_members_role_id_foreign` (`role_id`),
  KEY `cptm_assigned_by_idx` (`assigned_by_type`,`assigned_by_id`),
  CONSTRAINT `construction_project_team_members_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_project_team_members_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_project_team_members_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `construction_roles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_project_team_members`
--

LOCK TABLES `construction_project_team_members` WRITE;
/*!40000 ALTER TABLE `construction_project_team_members` DISABLE KEYS */;
INSERT INTO `construction_project_team_members` VALUES (1,1,5,6,'2026-07-31','2026-08-15','jjjjeuueue hehhrhhrh',1,'active','App\\Models\\SuperAdmin',1,'2026-07-30 03:12:31','2026-07-30 03:12:31'),(2,2,5,2,NULL,NULL,'hhhyyyy',0,'active','App\\Models\\SuperAdmin',1,'2026-07-31 05:40:28','2026-08-07 05:55:28'),(4,2,6,5,NULL,NULL,NULL,0,'active','App\\Models\\SuperAdmin',1,'2026-08-07 05:12:39','2026-08-07 05:12:39');
/*!40000 ALTER TABLE `construction_project_team_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_projects`
--

DROP TABLE IF EXISTS `construction_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned NOT NULL,
  `client_id` bigint(20) unsigned NOT NULL,
  `project_code` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `category` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `project_address` text DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expected_end_date` date DEFAULT NULL,
  `priority` varchar(255) NOT NULL DEFAULT 'medium',
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `current_stage` varchar(255) NOT NULL DEFAULT 'budget_pending',
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_projects_project_code_unique` (`project_code`),
  UNIQUE KEY `construction_projects_slug_unique` (`slug`),
  KEY `construction_projects_company_id_foreign` (`company_id`),
  KEY `construction_projects_client_id_foreign` (`client_id`),
  KEY `cproj_created_by_idx` (`created_by_type`,`created_by_id`),
  CONSTRAINT `construction_projects_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `construction_clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_projects_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `construction_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_projects`
--

LOCK TABLES `construction_projects` WRITE;
/*!40000 ALTER TABLE `construction_projects` DISABLE KEYS */;
INSERT INTO `construction_projects` VALUES (1,1,1,'PRJ-00001','dev tech','dev-tech-1','commercial',NULL,NULL,NULL,NULL,NULL,NULL,'medium','active','survey_planned','App\\Models\\SuperAdmin',1,'2026-07-30 03:07:35','2026-07-30 04:56:13',NULL),(2,2,2,'PRJ-00002','cadmax','cadmax-2','commercial','nothing','murlipura',26.9718000,75.7613000,'2026-08-12','2017-08-12','high','active','budget_approved','App\\Models\\SuperAdmin',1,'2026-07-31 05:38:22','2026-08-12 05:34:24',NULL);
/*!40000 ALTER TABLE `construction_projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_purchase_order_items`
--

DROP TABLE IF EXISTS `construction_purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_purchase_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_order_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `rate` decimal(12,2) DEFAULT NULL,
  `tax_percent` decimal(6,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(14,2) NOT NULL DEFAULT 0.00,
  `received_quantity` decimal(12,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cpoi_po_fk` (`purchase_order_id`),
  KEY `cpoi_material_fk` (`material_id`),
  CONSTRAINT `cpoi_material_fk` FOREIGN KEY (`material_id`) REFERENCES `construction_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpoi_po_fk` FOREIGN KEY (`purchase_order_id`) REFERENCES `construction_purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_purchase_order_items`
--

LOCK TABLES `construction_purchase_order_items` WRITE;
/*!40000 ALTER TABLE `construction_purchase_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_purchase_orders`
--

DROP TABLE IF EXISTS `construction_purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_purchase_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `purchase_request_id` bigint(20) unsigned DEFAULT NULL,
  `po_code` varchar(30) NOT NULL,
  `vendor_id` bigint(20) unsigned NOT NULL,
  `po_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `subtotal_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(14,2) NOT NULL DEFAULT 0.00,
  `invoice_document_id` bigint(20) unsigned DEFAULT NULL,
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_purchase_orders_po_code_unique` (`po_code`),
  KEY `construction_purchase_orders_created_by_type_created_by_id_index` (`created_by_type`,`created_by_id`),
  KEY `cpo_request_fk` (`purchase_request_id`),
  KEY `cpo_vendor_fk` (`vendor_id`),
  KEY `cpo_invoice_doc_fk` (`invoice_document_id`),
  KEY `construction_purchase_orders_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `construction_purchase_orders_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpo_invoice_doc_fk` FOREIGN KEY (`invoice_document_id`) REFERENCES `construction_documents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cpo_request_fk` FOREIGN KEY (`purchase_request_id`) REFERENCES `construction_purchase_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cpo_vendor_fk` FOREIGN KEY (`vendor_id`) REFERENCES `construction_vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_purchase_orders`
--

LOCK TABLES `construction_purchase_orders` WRITE;
/*!40000 ALTER TABLE `construction_purchase_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_purchase_request_items`
--

DROP TABLE IF EXISTS `construction_purchase_request_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_purchase_request_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `purchase_request_id` bigint(20) unsigned NOT NULL,
  `material_id` bigint(20) unsigned NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(50) DEFAULT NULL,
  `estimated_rate` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cpri_request_fk` (`purchase_request_id`),
  KEY `cpri_material_fk` (`material_id`),
  CONSTRAINT `cpri_material_fk` FOREIGN KEY (`material_id`) REFERENCES `construction_materials` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpri_request_fk` FOREIGN KEY (`purchase_request_id`) REFERENCES `construction_purchase_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_purchase_request_items`
--

LOCK TABLES `construction_purchase_request_items` WRITE;
/*!40000 ALTER TABLE `construction_purchase_request_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_purchase_request_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_purchase_requests`
--

DROP TABLE IF EXISTS `construction_purchase_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_purchase_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `request_code` varchar(30) NOT NULL,
  `requested_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `request_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `reviewed_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_purchase_requests_request_code_unique` (`request_code`),
  KEY `cpr_requested_by_fk` (`requested_by_member_id`),
  KEY `cpr_reviewed_by_fk` (`reviewed_by_member_id`),
  KEY `construction_purchase_requests_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `construction_purchase_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cpr_requested_by_fk` FOREIGN KEY (`requested_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cpr_reviewed_by_fk` FOREIGN KEY (`reviewed_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_purchase_requests`
--

LOCK TABLES `construction_purchase_requests` WRITE;
/*!40000 ALTER TABLE `construction_purchase_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_purchase_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_role_permissions`
--

DROP TABLE IF EXISTS `construction_role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_role_permissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` bigint(20) unsigned NOT NULL,
  `permission_id` bigint(20) unsigned NOT NULL,
  `surface` varchar(255) NOT NULL DEFAULT 'both',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_role_permissions_role_id_permission_id_unique` (`role_id`,`permission_id`),
  KEY `construction_role_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `construction_role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `construction_permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_role_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `construction_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_role_permissions`
--

LOCK TABLES `construction_role_permissions` WRITE;
/*!40000 ALTER TABLE `construction_role_permissions` DISABLE KEYS */;
INSERT INTO `construction_role_permissions` VALUES (1,1,34,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(2,1,14,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(3,1,15,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(4,1,29,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(5,1,30,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(6,1,2,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(7,1,1,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(8,1,35,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(9,1,33,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(10,1,12,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(11,1,13,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(12,1,8,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(13,1,9,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(14,1,27,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(15,1,28,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(16,1,26,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(17,1,11,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(18,1,10,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(19,1,31,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(20,1,21,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(21,1,20,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(22,1,22,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(23,1,17,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(24,1,4,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(25,1,32,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(26,1,5,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(27,1,3,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(28,1,19,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(29,1,18,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(30,1,6,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(31,1,7,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(32,1,24,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(33,1,25,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(34,1,23,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(35,1,16,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(36,2,34,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(37,2,14,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(38,2,15,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(39,2,29,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(40,2,30,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(41,2,35,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(42,2,33,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(43,2,12,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(44,2,13,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(45,2,8,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(46,2,9,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(47,2,27,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(48,2,28,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(49,2,26,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(50,2,11,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(51,2,10,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(52,2,31,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(53,2,21,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(54,2,20,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(55,2,22,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(56,2,17,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(57,2,4,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(58,2,32,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(59,2,5,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(60,2,3,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(61,2,19,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(62,2,18,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(63,2,6,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(64,2,7,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(65,2,24,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(66,2,25,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(67,2,23,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(68,2,16,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(69,3,33,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(70,3,6,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(71,4,33,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(72,4,8,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(73,5,34,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(74,5,15,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(75,5,35,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(76,5,13,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(77,5,9,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(78,5,22,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(79,5,7,'web','2026-07-21 03:26:39','2026-08-13 02:08:40'),(80,6,14,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(81,6,35,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(82,6,33,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(83,6,12,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(84,6,27,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(85,6,28,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(86,6,11,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(87,6,31,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(88,6,21,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(89,6,25,'both','2026-07-21 03:26:39','2026-07-21 03:26:39'),(90,1,36,'both','2026-08-13 02:08:40','2026-08-13 02:08:40'),(91,1,37,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(92,1,38,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(93,1,39,'both','2026-08-13 02:08:40','2026-08-13 02:08:40'),(94,1,40,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(95,1,41,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(96,1,42,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(97,1,43,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(98,1,44,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(99,1,45,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(100,1,46,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(101,3,36,'both','2026-08-13 02:08:40','2026-08-13 02:08:40'),(102,3,37,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(103,3,38,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(104,7,35,'both','2026-08-13 02:08:40','2026-08-13 02:08:40'),(105,7,25,'both','2026-08-13 02:08:40','2026-08-13 02:08:40'),(106,7,44,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(107,7,45,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(108,7,46,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(109,7,33,'both','2026-08-13 02:08:40','2026-08-13 02:08:40'),(110,6,39,'both','2026-08-13 02:08:40','2026-08-13 02:08:40'),(111,6,40,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(112,6,41,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(113,6,42,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(114,6,43,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40'),(115,6,46,'mobile','2026-08-13 02:08:40','2026-08-13 02:08:40');
/*!40000 ALTER TABLE `construction_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_roles`
--

DROP TABLE IF EXISTS `construction_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_system_role` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_roles_slug_unique` (`slug`),
  KEY `construction_roles_company_id_foreign` (`company_id`),
  CONSTRAINT `construction_roles_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `construction_companies` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_roles`
--

LOCK TABLES `construction_roles` WRITE;
/*!40000 ALTER TABLE `construction_roles` DISABLE KEYS */;
INSERT INTO `construction_roles` VALUES (1,NULL,'Super Admin','super_admin','Global construction ERP access',1,'active','2026-07-21 03:26:39','2026-07-21 03:26:39',NULL),(2,NULL,'Project Admin','project_admin','Project scoped ERP access',1,'active','2026-07-21 03:26:39','2026-07-21 03:26:39',NULL),(3,NULL,'Surveyor','surveyor','Field survey execution',1,'active','2026-07-21 03:26:39','2026-07-21 03:26:39',NULL),(4,NULL,'Draft Person','draft_person','Drafting and revisions',1,'active','2026-07-21 03:26:39','2026-07-21 03:26:39',NULL),(5,NULL,'Review Approver','review_approver','Workflow approvals',1,'active','2026-07-21 03:26:39','2026-07-21 03:26:39',NULL),(6,NULL,'Site Employee','site_employee','Construction execution updates and attendance',1,'active','2026-07-21 03:26:39','2026-07-21 03:26:39',NULL),(7,NULL,'Vehicle Driver','vehicle_driver','Vehicle transport and site movement',1,'active','2026-08-07 01:17:49','2026-08-07 01:17:49',NULL);
/*!40000 ALTER TABLE `construction_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_survey_entries`
--

DROP TABLE IF EXISTS `construction_survey_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_survey_entries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `survey_visit_id` bigint(20) unsigned NOT NULL,
  `entry_type` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `supporting_document_id` bigint(20) unsigned DEFAULT NULL,
  `captured_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `captured_at` timestamp NULL DEFAULT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_survey_entries_project_id_foreign` (`project_id`),
  KEY `construction_survey_entries_survey_visit_id_foreign` (`survey_visit_id`),
  KEY `construction_survey_entries_captured_by_member_id_foreign` (`captured_by_member_id`),
  KEY `cse_support_doc_fk` (`supporting_document_id`),
  CONSTRAINT `construction_survey_entries_captured_by_member_id_foreign` FOREIGN KEY (`captured_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_survey_entries_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_survey_entries_survey_visit_id_foreign` FOREIGN KEY (`survey_visit_id`) REFERENCES `construction_survey_visits` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cse_support_doc_fk` FOREIGN KEY (`supporting_document_id`) REFERENCES `construction_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_survey_entries`
--

LOCK TABLES `construction_survey_entries` WRITE;
/*!40000 ALTER TABLE `construction_survey_entries` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_survey_entries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_survey_measurements`
--

DROP TABLE IF EXISTS `construction_survey_measurements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_survey_measurements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `survey_visit_id` bigint(20) unsigned NOT NULL,
  `area_name` varchar(255) DEFAULT NULL,
  `measurement_type` varchar(255) NOT NULL DEFAULT 'room',
  `length` decimal(12,2) DEFAULT NULL,
  `width` decimal(12,2) DEFAULT NULL,
  `height` decimal(12,2) DEFAULT NULL,
  `unit` varchar(255) NOT NULL DEFAULT 'ft',
  `quantity` decimal(12,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `captured_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_survey_measurements_project_id_foreign` (`project_id`),
  KEY `construction_survey_measurements_survey_visit_id_foreign` (`survey_visit_id`),
  KEY `construction_survey_measurements_captured_by_member_id_foreign` (`captured_by_member_id`),
  CONSTRAINT `construction_survey_measurements_captured_by_member_id_foreign` FOREIGN KEY (`captured_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_survey_measurements_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_survey_measurements_survey_visit_id_foreign` FOREIGN KEY (`survey_visit_id`) REFERENCES `construction_survey_visits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_survey_measurements`
--

LOCK TABLES `construction_survey_measurements` WRITE;
/*!40000 ALTER TABLE `construction_survey_measurements` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_survey_measurements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_survey_plan_members`
--

DROP TABLE IF EXISTS `construction_survey_plan_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_survey_plan_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `survey_plan_id` bigint(20) unsigned NOT NULL,
  `member_id` bigint(20) unsigned NOT NULL,
  `role_in_survey` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'assigned',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_survey_plan_member_unique` (`survey_plan_id`,`member_id`),
  KEY `construction_survey_plan_members_member_id_foreign` (`member_id`),
  CONSTRAINT `construction_survey_plan_members_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_survey_plan_members_survey_plan_id_foreign` FOREIGN KEY (`survey_plan_id`) REFERENCES `construction_survey_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_survey_plan_members`
--

LOCK TABLES `construction_survey_plan_members` WRITE;
/*!40000 ALTER TABLE `construction_survey_plan_members` DISABLE KEYS */;
INSERT INTO `construction_survey_plan_members` VALUES (1,2,5,'surveyor','assigned','2026-07-31 05:43:08','2026-07-31 05:43:08');
/*!40000 ALTER TABLE `construction_survey_plan_members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_survey_plans`
--

DROP TABLE IF EXISTS `construction_survey_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_survey_plans` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `survey_code` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `site_address` text DEFAULT NULL,
  `site_latitude` decimal(10,7) DEFAULT NULL,
  `site_longitude` decimal(10,7) DEFAULT NULL,
  `planned_date` date DEFAULT NULL,
  `planned_start_time` time DEFAULT NULL,
  `planned_end_time` time DEFAULT NULL,
  `assigned_by_type` varchar(255) DEFAULT NULL,
  `assigned_by_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'planned',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_survey_plans_survey_code_unique` (`survey_code`),
  KEY `construction_survey_plans_project_id_foreign` (`project_id`),
  KEY `csp_assigned_by_idx` (`assigned_by_type`,`assigned_by_id`),
  CONSTRAINT `construction_survey_plans_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_survey_plans`
--

LOCK TABLES `construction_survey_plans` WRITE;
/*!40000 ALTER TABLE `construction_survey_plans` DISABLE KEYS */;
INSERT INTO `construction_survey_plans` VALUES (1,1,'SUR-00001','metro','jhdewhd','jewkndwjn',26.9124340,75.7872710,'2026-08-01','00:12:00','12:12:00','App\\Models\\SuperAdmin',1,'planned','2026-07-30 04:56:13','2026-07-30 04:56:13'),(2,2,'SUR-00002','land survey','survey','nothing',26.9718000,75.7613000,'2026-08-26','11:00:00','18:00:00','App\\Models\\SuperAdmin',1,'planned','2026-07-31 05:43:08','2026-07-31 05:43:08');
/*!40000 ALTER TABLE `construction_survey_plans` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_survey_submissions`
--

DROP TABLE IF EXISTS `construction_survey_submissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_survey_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `survey_visit_id` bigint(20) unsigned NOT NULL,
  `submitted_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'draft',
  `review_notes` text DEFAULT NULL,
  `reviewed_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_survey_submissions_project_id_foreign` (`project_id`),
  KEY `construction_survey_submissions_survey_visit_id_foreign` (`survey_visit_id`),
  KEY `construction_survey_submissions_submitted_by_member_id_foreign` (`submitted_by_member_id`),
  KEY `construction_survey_submissions_reviewed_by_member_id_foreign` (`reviewed_by_member_id`),
  CONSTRAINT `construction_survey_submissions_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_survey_submissions_reviewed_by_member_id_foreign` FOREIGN KEY (`reviewed_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_survey_submissions_submitted_by_member_id_foreign` FOREIGN KEY (`submitted_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `construction_survey_submissions_survey_visit_id_foreign` FOREIGN KEY (`survey_visit_id`) REFERENCES `construction_survey_visits` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_survey_submissions`
--

LOCK TABLES `construction_survey_submissions` WRITE;
/*!40000 ALTER TABLE `construction_survey_submissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_survey_submissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_survey_visits`
--

DROP TABLE IF EXISTS `construction_survey_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_survey_visits` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `survey_plan_id` bigint(20) unsigned NOT NULL,
  `checked_in_by_member_id` bigint(20) unsigned NOT NULL,
  `check_in_at` timestamp NULL DEFAULT NULL,
  `check_in_latitude` decimal(10,7) DEFAULT NULL,
  `check_in_longitude` decimal(10,7) DEFAULT NULL,
  `gps_distance_meters` decimal(10,2) DEFAULT NULL,
  `gps_verified` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'checked_in',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `construction_survey_visits_project_id_foreign` (`project_id`),
  KEY `construction_survey_visits_survey_plan_id_foreign` (`survey_plan_id`),
  KEY `construction_survey_visits_checked_in_by_member_id_foreign` (`checked_in_by_member_id`),
  CONSTRAINT `construction_survey_visits_checked_in_by_member_id_foreign` FOREIGN KEY (`checked_in_by_member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_survey_visits_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `construction_survey_visits_survey_plan_id_foreign` FOREIGN KEY (`survey_plan_id`) REFERENCES `construction_survey_plans` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_survey_visits`
--

LOCK TABLES `construction_survey_visits` WRITE;
/*!40000 ALTER TABLE `construction_survey_visits` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_survey_visits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_vehicle_assignments`
--

DROP TABLE IF EXISTS `construction_vehicle_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_vehicle_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `vehicle_id` bigint(20) unsigned NOT NULL,
  `driver_member_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_from` timestamp NULL DEFAULT NULL,
  `assigned_to` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `assigned_by_type` varchar(255) DEFAULT NULL,
  `assigned_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cva_assigned_by_idx` (`assigned_by_type`,`assigned_by_id`),
  KEY `cva_vehicle_fk` (`vehicle_id`),
  KEY `cva_driver_fk` (`driver_member_id`),
  KEY `cva_project_vehicle_status_idx` (`project_id`,`vehicle_id`,`status`),
  CONSTRAINT `construction_vehicle_assignments_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cva_driver_fk` FOREIGN KEY (`driver_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cva_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `construction_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_vehicle_assignments`
--

LOCK TABLES `construction_vehicle_assignments` WRITE;
/*!40000 ALTER TABLE `construction_vehicle_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_vehicle_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_vehicle_location_pings`
--

DROP TABLE IF EXISTS `construction_vehicle_location_pings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_vehicle_location_pings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `vehicle_id` bigint(20) unsigned NOT NULL,
  `reported_by_member_id` bigint(20) unsigned DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `gps_accuracy_meters` decimal(10,2) DEFAULT NULL,
  `speed_kmph` decimal(10,2) DEFAULT NULL,
  `heading_degrees` decimal(10,2) DEFAULT NULL,
  `odometer_km` decimal(12,2) DEFAULT NULL,
  `gps_verified` tinyint(1) NOT NULL DEFAULT 0,
  `source` varchar(30) NOT NULL DEFAULT 'web',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cvlp_vehicle_fk` (`vehicle_id`),
  KEY `cvlp_reported_by_fk` (`reported_by_member_id`),
  KEY `cvlp_project_vehicle_time_idx` (`project_id`,`vehicle_id`,`recorded_at`),
  KEY `cvlp_project_verified_idx` (`project_id`,`gps_verified`),
  CONSTRAINT `construction_vehicle_location_pings_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cvlp_reported_by_fk` FOREIGN KEY (`reported_by_member_id`) REFERENCES `members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `cvlp_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `construction_vehicles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_vehicle_location_pings`
--

LOCK TABLES `construction_vehicle_location_pings` WRITE;
/*!40000 ALTER TABLE `construction_vehicle_location_pings` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_vehicle_location_pings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_vehicles`
--

DROP TABLE IF EXISTS `construction_vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_vehicles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `vehicle_code` varchar(30) NOT NULL,
  `registration_number` varchar(30) NOT NULL,
  `vehicle_type` varchar(50) DEFAULT NULL,
  `make` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cv_project_reg_unique` (`project_id`,`registration_number`),
  UNIQUE KEY `construction_vehicles_vehicle_code_unique` (`vehicle_code`),
  KEY `cv_created_by_idx` (`created_by_type`,`created_by_id`),
  KEY `construction_vehicles_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `construction_vehicles_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_vehicles`
--

LOCK TABLES `construction_vehicles` WRITE;
/*!40000 ALTER TABLE `construction_vehicles` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `construction_vendors`
--

DROP TABLE IF EXISTS `construction_vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `construction_vendors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint(20) unsigned NOT NULL,
  `vendor_code` varchar(30) NOT NULL,
  `name` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `gstin` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_by_type` varchar(255) DEFAULT NULL,
  `created_by_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `construction_vendors_vendor_code_unique` (`vendor_code`),
  KEY `construction_vendors_created_by_type_created_by_id_index` (`created_by_type`,`created_by_id`),
  KEY `construction_vendors_project_id_status_index` (`project_id`,`status`),
  CONSTRAINT `construction_vendors_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `construction_projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `construction_vendors`
--

LOCK TABLES `construction_vendors` WRITE;
/*!40000 ALTER TABLE `construction_vendors` DISABLE KEYS */;
/*!40000 ALTER TABLE `construction_vendors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contact_messages`
--

DROP TABLE IF EXISTS `contact_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contact_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(255) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_messages_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contact_messages`
--

LOCK TABLES `contact_messages` WRITE;
/*!40000 ALTER TABLE `contact_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `contact_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `designations`
--

DROP TABLE IF EXISTS `designations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `designations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `department_id` bigint(20) unsigned DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `designations_slug_unique` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `designations`
--

LOCK TABLES `designations` WRITE;
/*!40000 ALTER TABLE `designations` DISABLE KEYS */;
/*!40000 ALTER TABLE `designations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `super_admin_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `extension` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_logs`
--

DROP TABLE IF EXISTS `email_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `task_id` bigint(20) unsigned DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `to` varchar(255) DEFAULT NULL,
  `from` varchar(255) DEFAULT NULL,
  `body_html` longtext DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `error_code` varchar(100) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_logs`
--

LOCK TABLES `email_logs` WRITE;
/*!40000 ALTER TABLE `email_logs` DISABLE KEYS */;
INSERT INTO `email_logs` VALUES (1,'019f83a2-ce34-70c3-8a55-b50a413f8f51',1,NULL,'Account Creation Notification','ptest@gmail.com','uniquetech.supt@gmail.com','Account created for Pradeep Saini with username: radeepaini and login URL: http://127.0.0.1:8000/login','sent','2026-07-21 02:15:12',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-21 02:15:12','2026-07-21 02:15:12'),(2,'019f83c7-6ac4-72fc-b280-258d656062c9',1,NULL,'Account Creation Notification','testmember@gmail.com','uniquetech.supt@gmail.com','Account created for Pradeep Saini with username: radeepaini1 and login URL: http://127.0.0.1:8000/login','sent','2026-07-21 02:55:12',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-21 02:55:12','2026-07-21 02:55:12'),(3,'019f88e5-cce3-7265-9e27-ecfae2b769a0',1,NULL,'Account Creation Notification','aaravs@gmail.com','uniquetech.supt@gmail.com','Account created for aarav with username: aarav and login URL: http://127.0.0.1:8000/login','sent','2026-07-22 02:46:29',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36 Edg/150.0.0.0','2026-07-22 02:46:29','2026-07-22 02:46:29'),(4,'019fb81c-849c-72c1-b81f-22cf6ede5d9d',1,NULL,'Account Creation Notification','pradeep2206@gmail.com','uniquetech.supt@gmail.com','Account created for Pradeep Saini with username: radeepaini and login URL: http://localhost:8000/login','sent','2026-07-31 06:48:24',NULL,NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','2026-07-31 06:48:24','2026-07-31 06:48:24');
/*!40000 ALTER TABLE `email_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `member_id` bigint(20) unsigned NOT NULL,
  `employee_id` varchar(255) NOT NULL,
  `alternate_number` varchar(255) DEFAULT NULL,
  `aadhaar_number` varchar(255) DEFAULT NULL,
  `pan_number` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_member_id_unique` (`member_id`),
  UNIQUE KEY `employees_employee_id_unique` (`employee_id`),
  UNIQUE KEY `employees_aadhaar_number_unique` (`aadhaar_number`),
  UNIQUE KEY `employees_pan_number_unique` (`pan_number`),
  KEY `employees_employee_id_index` (`employee_id`),
  CONSTRAINT `employees_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (2,'019f83c7-6ac7-730c-9f0b-f9766706ae7e',4,'EMP-000002',NULL,NULL,NULL,'2026-07-21 02:55:12','2026-07-21 02:55:12',NULL),(3,'019f88e5-ccf9-7030-b1cd-af3f327a279b',5,'EMP-000003',NULL,NULL,NULL,'2026-07-22 02:46:29','2026-07-22 02:46:29',NULL),(4,'019fb81c-84e6-717f-928c-1a426702f418',6,'EMP-236176','jjsjsjjsjjs','jkskjsjsj',NULL,'2026-07-31 06:48:24','2026-07-31 06:48:24',NULL);
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment_categories`
--

DROP TABLE IF EXISTS `equipment_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment_categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` varchar(255) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipment_categories_category_id_unique` (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment_categories`
--

LOCK TABLES `equipment_categories` WRITE;
/*!40000 ALTER TABLE `equipment_categories` DISABLE KEYS */;
INSERT INTO `equipment_categories` VALUES (1,'EC-225628','drown','kjduqiedeiud','1','2026-07-29 06:24:25','2026-07-30 01:51:26',NULL);
/*!40000 ALTER TABLE `equipment_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipments`
--

DROP TABLE IF EXISTS `equipments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `equipment_id` varchar(255) NOT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `serial_number` varchar(255) DEFAULT NULL,
  `asset_tag` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(15,2) DEFAULT NULL,
  `vendor` varchar(255) DEFAULT NULL,
  `warranty_till` date DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'available',
  `assigned_employee_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_project_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `equipments_equipment_id_unique` (`equipment_id`),
  KEY `equipments_assigned_employee_id_foreign` (`assigned_employee_id`),
  KEY `equipments_assigned_project_id_foreign` (`assigned_project_id`),
  KEY `equipments_category_id_foreign` (`category_id`),
  CONSTRAINT `equipments_assigned_employee_id_foreign` FOREIGN KEY (`assigned_employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipments_assigned_project_id_foreign` FOREIGN KEY (`assigned_project_id`) REFERENCES `construction_projects` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipments_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `equipment_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipments`
--

LOCK TABLES `equipments` WRITE;
/*!40000 ALTER TABLE `equipments` DISABLE KEYS */;
INSERT INTO `equipments` VALUES (1,'EQ-482297',1,'dgps drown','sony','sony','7362','1234567890','jsjsjjs','2026-07-12',790000.00,'mukesh','2026-09-30',NULL,'available',2,NULL,'2026-07-28','2026-07-29 06:27:12','2026-07-29 06:27:12',NULL);
/*!40000 ALTER TABLE `equipments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fcm_tokens`
--

DROP TABLE IF EXISTS `fcm_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fcm_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `guard` varchar(255) NOT NULL,
  `device_id` text DEFAULT NULL,
  `token` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fcm_tokens`
--

LOCK TABLES `fcm_tokens` WRITE;
/*!40000 ALTER TABLE `fcm_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `fcm_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `holidays`
--

DROP TABLE IF EXISTS `holidays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `holidays` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `super_admin_id` bigint(20) unsigned DEFAULT NULL,
  `date` date NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `holidays`
--

LOCK TABLES `holidays` WRITE;
/*!40000 ALTER TABLE `holidays` DISABLE KEYS */;
/*!40000 ALTER TABLE `holidays` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `image_action_logs`
--

DROP TABLE IF EXISTS `image_action_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `image_action_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `super_admin_id` bigint(20) unsigned NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `action` enum('uploaded','deleted') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `image_action_logs`
--

LOCK TABLES `image_action_logs` WRITE;
/*!40000 ALTER TABLE `image_action_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `image_action_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_applications`
--

DROP TABLE IF EXISTS `job_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_applications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `job_id` bigint(20) unsigned NOT NULL,
  `candidate_id` bigint(20) unsigned NOT NULL,
  `cover_letter` text DEFAULT NULL,
  `resume_url` varchar(255) DEFAULT NULL,
  `answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`answers`)),
  `screening_answers` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`screening_answers`)),
  `status` varchar(100) NOT NULL DEFAULT 'applied',
  `admin_notes` text DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `assigned_calling_team_member_id` bigint(20) unsigned DEFAULT NULL,
  `assigned_to_calling_team_at` timestamp NULL DEFAULT NULL,
  `call_outcome` varchar(255) DEFAULT NULL,
  `call_outcome_reason` text DEFAULT NULL,
  `call_notes` text DEFAULT NULL,
  `interview_date_time` timestamp NULL DEFAULT NULL,
  `interview_mode` varchar(255) DEFAULT NULL,
  `interview_address` text DEFAULT NULL,
  `interview_instructions` text DEFAULT NULL,
  `interview_contact_person` varchar(255) DEFAULT NULL,
  `interview_confirmed_at` timestamp NULL DEFAULT NULL,
  `offer_letter_triggered_at` timestamp NULL DEFAULT NULL,
  `hiring_decision` varchar(255) DEFAULT NULL,
  `hiring_decision_reason` text DEFAULT NULL,
  `hiring_decision_updated_at` timestamp NULL DEFAULT NULL,
  `admin_final_decision` varchar(255) DEFAULT NULL,
  `admin_final_decision_reason` text DEFAULT NULL,
  `admin_final_decision_updated_at` timestamp NULL DEFAULT NULL,
  `offer_salary_package` varchar(255) DEFAULT NULL,
  `offer_joining_date` date DEFAULT NULL,
  `offer_letter_path` varchar(255) DEFAULT NULL,
  `offer_letter_sent_at` timestamp NULL DEFAULT NULL,
  `candidate_name` varchar(255) NOT NULL,
  `candidate_email` varchar(255) NOT NULL,
  `candidate_phone` varchar(255) DEFAULT NULL,
  `candidate_skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`candidate_skills`)),
  `candidate_experience` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_applications_uuid_unique` (`uuid`),
  KEY `job_applications_reviewed_by_foreign` (`reviewed_by`),
  KEY `job_applications_candidate_id_status_index` (`candidate_id`,`status`),
  KEY `job_applications_job_id_status_index` (`job_id`,`status`),
  KEY `job_applications_calling_member_idx` (`assigned_calling_team_member_id`),
  CONSTRAINT `job_applications_candidate_id_foreign` FOREIGN KEY (`candidate_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_applications_job_id_foreign` FOREIGN KEY (`job_id`) REFERENCES `job_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `job_applications_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `members` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_applications`
--

LOCK TABLES `job_applications` WRITE;
/*!40000 ALTER TABLE `job_applications` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_applications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_posts`
--

DROP TABLE IF EXISTS `job_posts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_posts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `title` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'Job location latitude',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'Job location longitude',
  `job_type` varchar(255) NOT NULL,
  `openings` int(10) unsigned NOT NULL DEFAULT 1,
  `experience` varchar(255) DEFAULT NULL,
  `min_age` tinyint(3) unsigned DEFAULT NULL,
  `max_age` tinyint(3) unsigned DEFAULT NULL,
  `salary` varchar(255) DEFAULT NULL,
  `skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills`)),
  `perks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`perks`)),
  `key_responsibilities` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`key_responsibilities`)),
  `qualifications` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`qualifications`)),
  `assets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`assets`)),
  `application_questions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`application_questions`)),
  `last_date` date DEFAULT NULL,
  `company_image` varchar(255) DEFAULT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(30) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `applicants` int(11) NOT NULL DEFAULT 0,
  `status` enum('pending','active','inactive','declined','closed') NOT NULL DEFAULT 'pending',
  `created_by` bigint(20) unsigned NOT NULL,
  `approved_by` bigint(20) unsigned DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `resubmitted_at` timestamp NULL DEFAULT NULL,
  `approval_logs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`approval_logs`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_posts_uuid_unique` (`uuid`),
  KEY `job_posts_created_by_foreign` (`created_by`),
  KEY `job_posts_approved_by_foreign` (`approved_by`),
  CONSTRAINT `job_posts_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `super_admins` (`id`) ON DELETE SET NULL,
  CONSTRAINT `job_posts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_posts`
--

LOCK TABLES `job_posts` WRITE;
/*!40000 ALTER TABLE `job_posts` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_posts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `type` enum('sick','vacation','personal','other') NOT NULL,
  `reason` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `is_email` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `member_roles`
--

DROP TABLE IF EXISTS `member_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `member_roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `role_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `member_roles_member_id_index` (`member_id`),
  KEY `member_roles_role_id_index` (`role_id`),
  CONSTRAINT `member_roles_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `member_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `member_roles`
--

LOCK TABLES `member_roles` WRITE;
/*!40000 ALTER TABLE `member_roles` DISABLE KEYS */;
/*!40000 ALTER TABLE `member_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `members`
--

DROP TABLE IF EXISTS `members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `assigned_admin_id` bigint(20) unsigned DEFAULT NULL,
  `is_calling_team` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT '1',
  `roles` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`roles`)),
  `designation` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`designation`)),
  `departments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`departments`)),
  `slug` varchar(255) NOT NULL,
  `otp` varchar(255) DEFAULT NULL,
  `otp_expire` timestamp NULL DEFAULT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `phone_verify_at` timestamp NULL DEFAULT NULL,
  `image` text DEFAULT NULL,
  `candidate_profile` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`candidate_profile`)),
  `resume_path` varchar(255) DEFAULT NULL,
  `resume_original_name` varchar(255) DEFAULT NULL,
  `resume_mime` varchar(255) DEFAULT NULL,
  `resume_size` bigint(20) unsigned DEFAULT NULL,
  `resume_uploaded_at` timestamp NULL DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `reset_password_token` varchar(255) DEFAULT NULL,
  `reset_password_token_expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `members_username_unique` (`username`),
  UNIQUE KEY `members_slug_unique` (`slug`),
  UNIQUE KEY `members_email_unique` (`email`),
  UNIQUE KEY `members_phone_unique` (`phone`),
  KEY `members_assigned_admin_calling_idx` (`assigned_admin_id`,`is_calling_team`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `members`
--

LOCK TABLES `members` WRITE;
/*!40000 ALTER TABLE `members` DISABLE KEYS */;
INSERT INTO `members` VALUES (4,'019f83c7-5428-71f0-b149-4702538f26cb',1,NULL,0,'Pradeep Saini','radeepaini1','testmember@gmail.com','9119377641','$2y$12$jh.TEv5sifXRlg5vlpkuHuPX7Ulr.B1GI52lXvcfWQYRAAQ6OxiCq',0,'1','[3]','[\"Civil Engineer\"]','[\"Survey\"]','pradeep-saini-vqar',NULL,NULL,'2002-09-09','male',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-21 02:55:06','2026-08-12 07:03:26',NULL,NULL,NULL),(5,'019f88e5-b32a-7352-bc01-8a553d4707c6',1,NULL,0,'aarav','aarav','aaravs@gmail.com','9090898900','$2y$12$TYwYPgsOmrtunNHdwCiU3u4fCGdsvDXYfYy5KDZIsBPY1/BjEBewa',0,'0','[1]','[\"Senior Architect\"]','[\"Architecture\"]','aarav-jutq',NULL,NULL,'2002-07-09','male',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-22 02:46:22','2026-08-12 07:04:35',NULL,NULL,NULL),(6,'019fb81c-5fd2-731f-8328-8d361085e94d',1,NULL,0,'Pradeep Saini','radeepaini','pradeep2206@gmail.com','9090787867','$2y$12$rIFR6NOEoVjZITMacF39f.lsz4nhu/Q1b81MvFBuDnANLJHd9hw0W',0,'1','[1]','[\"Manager (Engineering)\"]','[\"Engineering\"]','pradeep-saini-iqbt',NULL,NULL,'2002-06-22','male','2026-07-31 07:05:34',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-07-31 06:48:15','2026-08-11 01:22:17',NULL,NULL,NULL);
/*!40000 ALTER TABLE `members` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000001_create_cache_table',1),(2,'0001_01_01_000002_create_jobs_table',1),(3,'2025_05_23_071221_create_system_settings_table',1),(4,'2025_06_02_093706_create_super_admins_table',1),(5,'2025_06_17_090912_create_sessions_table',1),(6,'2025_06_17_091113_create_users_table',1),(7,'2025_06_19_045433_add_username_to_super_admins_table',1),(8,'2025_06_19_071434_create_roles_table',1),(9,'2025_06_20_063934_create_members_table',1),(10,'2025_06_21_043520_rename_roles_table_to_departments',1),(11,'2025_06_21_051505_create_roles_table',1),(12,'2025_06_21_054827_add_department_id_to_members_table',1),(13,'2025_06_23_112912_create_designations_table',1),(14,'2025_06_23_114036_add_json_designation_to_members_table',1),(15,'2025_06_26_105825_create_tasks_table',1),(16,'2025_06_30_125039_add_task_type_and_recurring_columns_to_tasks_table',1),(17,'2025_07_01_042744_create_task_assignments_table',1),(18,'2025_07_01_043452_create_task_logs_table',1),(19,'2025_07_01_045142_create_task_activity_logs_table',1),(20,'2025_07_01_050813_create_task_comments_table',1),(21,'2025_07_01_070215_add_columns_to_task_logs_table',1),(22,'2025_07_01_124826_create_task_instances_table',1),(23,'2025_07_02_050824_add_is_admin_to_task_activity_logs_table',1),(24,'2025_07_03_052526_add_specific_date_to_task_table',1),(25,'2025_07_04_105525_update_super_admins_nullable_columns',1),(26,'2025_07_08_070955_add_tracking_columns_to_task_assignments_table',1),(27,'2025_07_09_052543_add_reply_note_id_to_task_comments_table',1),(28,'2025_07_18_045341_create_notifications_table',1),(29,'2025_07_18_051723_create_notification_settings_table',1),(30,'2025_07_22_115717_create_fcm_tokens_table',1),(31,'2025_07_28_094708_add_profile_image_to_super_admins_table',1),(32,'2025_07_30_054224_update_tasks_status_default_to_running',1),(33,'2025_07_30_110739_create_email_logs_table',1),(34,'2025_07_31_063205_add_to_and_from_to_email_logs_table',1),(35,'2025_08_02_100934_add_password_reset_columns_to_members_table',1),(36,'2025_08_04_065855_create_activity_logs_table',1),(37,'2025_08_06_121236_create_site_settings_table',1),(38,'2025_08_18_051313_create_task_documents_table',1),(39,'2025_08_18_064651_add_type_to_task_documents_table',1),(40,'2025_08_18_110243_drop_fcm_tokens_table',1),(41,'2025_08_18_110333_create_fcm_tokens_table',1),(42,'2025_08_19_065347_create_notification_logs_table',1),(43,'2025_08_29_095028_create_stages_table',1),(44,'2025_08_29_104505_add_is_stage_to_tasks_table',1),(45,'2025_09_04_050332_create_documents_table',1),(46,'2025_09_04_111240_create_staff_documents_table',1),(47,'2025_09_08_094434_add_reset_password_fields_to_super_admins_table',1),(48,'2025_09_08_105901_create_super_admin_password_logs_table',1),(49,'2025_09_10_124444_create_image_action_logs_table',1),(50,'2025_09_11_044511_create_whatsapp_logs_table',1),(51,'2025_09_11_092120_update_nullable_columns_in_super_admin_password_logs',1),(52,'2025_09_12_073041_alter_image_column_in_members_table',1),(53,'2025_09_12_073649_add_image_to_members_table',1),(54,'2025_09_15_121922_create_holidays_table',1),(55,'2025_09_15_122703_create_leave_requests_table',1),(56,'2025_09_15_124601_create_calendar_notes_table',1),(57,'2025_09_15_125532_create_check_in_outs_table',1),(58,'2025_10_14_063318_add_status_to_holidays_table',1),(59,'2026_03_17_000001_create_resumes_and_related_tables',1),(60,'2026_04_01_000000_drop_job_posts_table',1),(61,'2026_04_01_000001_create_jobs_table',1),(62,'2026_04_02_100000_add_inactive_to_job_posts_status',1),(63,'2026_04_02_110000_add_closed_to_job_posts_status',1),(64,'2026_04_06_000001_create_job_applications_table',1),(65,'2026_04_07_000001_add_post_new_job_fields_to_job_posts_table',1),(66,'2026_04_10_074751_create_personal_access_tokens_table',1),(67,'2026_04_22_000001_add_candidate_profile_to_members_table',1),(68,'2026_04_27_000001_create_saved_jobs_table',1),(69,'2026_04_27_000002_add_resume_fields_to_members_table',1),(70,'2026_04_28_053323_update_job_applications_status_enum',1),(71,'2026_05_01_120001_add_lat_long_to_users_table',1),(72,'2026_05_01_120002_add_lat_long_to_job_posts_table',1),(73,'2026_05_05_173555_create_member_roles_table',1),(74,'2026_05_06_000001_create_contact_messages_table',1),(75,'2026_05_07_000001_drop_notifications_table',1),(76,'2026_05_07_000001_rebuild_notifications_table',1),(77,'2026_05_12_000001_add_assigned_admin_id_to_members_table',1),(78,'2026_05_15_000001_add_calling_team_flow_to_members_and_job_applications',1),(79,'2026_05_20_000001_add_hiring_decision_fields_to_job_applications',1),(80,'2026_05_20_000002_add_admin_final_review_fields_to_job_applications',1),(81,'2026_05_20_000003_add_offer_letter_fields_to_job_applications',1),(82,'2026_05_20_000004_align_job_application_status_flow',1),(83,'2026_05_22_000001_add_must_change_password_to_members_table',1),(84,'2026_05_26_111500_fix_job_applications_status_column_to_string',1),(85,'2026_05_30_120000_add_remember_token_to_super_admins_table',1),(86,'2026_06_04_085217_update_job_posts_json_fields',1),(87,'2026_06_19_000001_add_application_questions_to_job_posts_table',1),(88,'2026_06_19_000002_add_screening_answers_to_job_applications_table',1),(89,'2026_07_12_150000_create_construction_erp_foundation_tables',1),(90,'2026_07_12_170000_drop_unused_construction_tables',1),(91,'2026_07_12_190000_create_construction_execution_tables',1),(92,'2026_07_13_090000_create_construction_material_management_tables',2),(93,'2026_07_13_120000_create_construction_vehicle_tracking_tables',2),(94,'2026_07_13_130000_create_construction_equipment_allocation_tables',2),(95,'2026_07_13_140000_create_construction_billing_tables',2),(96,'2026_07_13_150000_create_construction_handover_tables',2),(97,'2026_07_13_160000_add_documents_to_construction_material_receipts',2),(98,'2026_07_13_170000_add_documents_to_construction_survey_entries_and_dprs',2),(99,'2026_07_20_000001_create_employees_table',2),(100,'2026_07_20_000002_restructure_employees_table',3),(101,'2026_07_23_000001_create_vehicles_table',4),(102,'2026_07_29_112141_create_equipment_categories_table',5),(103,'2026_07_29_112159_create_equipments_table',5),(105,'2026_07_30_000003_update_vehicles_status_to_integer',5),(106,'2026_07_30_061936_update_equipments_table',6),(107,'2026_08_13_000001_enhance_construction_member_role_assignments',7),(108,'2026_08_19_071950_convert_member_role_assignment_status_to_integer',8);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_logs`
--

DROP TABLE IF EXISTS `notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `morphs` text DEFAULT NULL,
  `data` text DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `channel` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'sent',
  `error` text DEFAULT NULL,
  `redirect_url` varchar(255) DEFAULT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `sender_id` bigint(20) unsigned DEFAULT NULL,
  `receiver_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_logs`
--

LOCK TABLES `notification_logs` WRITE;
/*!40000 ALTER TABLE `notification_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notification_settings`
--

DROP TABLE IF EXISTS `notification_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_settings` (
  `id` char(36) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL COMMENT 'action name',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 - On, 0 - Off',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notification_settings`
--

LOCK TABLES `notification_settings` WRITE;
/*!40000 ALTER TABLE `notification_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `notification_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `model` varchar(255) NOT NULL,
  `listing_id` bigint(20) unsigned NOT NULL,
  `job_id` bigint(20) unsigned DEFAULT NULL,
  `type` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'unread',
  `viewed_at` timestamp NULL DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `notifications_uuid_unique` (`uuid`),
  KEY `notifications_model_listing_id_status_index` (`model`,`listing_id`,`status`),
  KEY `notifications_model_listing_id_viewed_at_index` (`model`,`listing_id`,`viewed_at`),
  KEY `notifications_job_id_type_index` (`job_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personal_access_tokens`
--

DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personal_access_tokens`
--

LOCK TABLES `personal_access_tokens` WRITE;
/*!40000 ALTER TABLE `personal_access_tokens` DISABLE KEYS */;
INSERT INTO `personal_access_tokens` VALUES (6,'App\\Models\\Member',6,'Postman Testing','d4e85a3c239000aa76b6bc5fad5e29552f12f25b55dacea0182e6beb12faa401','[\"*\"]',NULL,NULL,'2026-07-31 07:00:06','2026-07-31 07:00:06');
/*!40000 ALTER TABLE `personal_access_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resume_achievements`
--

DROP TABLE IF EXISTS `resume_achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resume_achievements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resume_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resume_achievements_resume_id_foreign` (`resume_id`),
  CONSTRAINT `resume_achievements_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resume_achievements`
--

LOCK TABLES `resume_achievements` WRITE;
/*!40000 ALTER TABLE `resume_achievements` DISABLE KEYS */;
/*!40000 ALTER TABLE `resume_achievements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resume_certifications`
--

DROP TABLE IF EXISTS `resume_certifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resume_certifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resume_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `year` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resume_certifications_resume_id_foreign` (`resume_id`),
  CONSTRAINT `resume_certifications_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resume_certifications`
--

LOCK TABLES `resume_certifications` WRITE;
/*!40000 ALTER TABLE `resume_certifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `resume_certifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resume_educations`
--

DROP TABLE IF EXISTS `resume_educations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resume_educations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resume_id` bigint(20) unsigned NOT NULL,
  `degree` varchar(255) DEFAULT NULL,
  `institute` varchar(255) DEFAULT NULL,
  `start_year` varchar(255) DEFAULT NULL,
  `end_year` varchar(255) DEFAULT NULL,
  `percentage` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resume_educations_resume_id_foreign` (`resume_id`),
  CONSTRAINT `resume_educations_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resume_educations`
--

LOCK TABLES `resume_educations` WRITE;
/*!40000 ALTER TABLE `resume_educations` DISABLE KEYS */;
/*!40000 ALTER TABLE `resume_educations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resume_experiences`
--

DROP TABLE IF EXISTS `resume_experiences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resume_experiences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resume_id` bigint(20) unsigned NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `job_title` varchar(255) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resume_experiences_resume_id_foreign` (`resume_id`),
  CONSTRAINT `resume_experiences_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resume_experiences`
--

LOCK TABLES `resume_experiences` WRITE;
/*!40000 ALTER TABLE `resume_experiences` DISABLE KEYS */;
/*!40000 ALTER TABLE `resume_experiences` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resume_languages`
--

DROP TABLE IF EXISTS `resume_languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resume_languages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resume_id` bigint(20) unsigned NOT NULL,
  `language` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resume_languages_resume_id_foreign` (`resume_id`),
  CONSTRAINT `resume_languages_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resume_languages`
--

LOCK TABLES `resume_languages` WRITE;
/*!40000 ALTER TABLE `resume_languages` DISABLE KEYS */;
/*!40000 ALTER TABLE `resume_languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resume_projects`
--

DROP TABLE IF EXISTS `resume_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resume_projects` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resume_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `technologies` varchar(255) DEFAULT NULL,
  `project_link` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resume_projects_resume_id_foreign` (`resume_id`),
  CONSTRAINT `resume_projects_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resume_projects`
--

LOCK TABLES `resume_projects` WRITE;
/*!40000 ALTER TABLE `resume_projects` DISABLE KEYS */;
/*!40000 ALTER TABLE `resume_projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resume_skills`
--

DROP TABLE IF EXISTS `resume_skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resume_skills` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resume_id` bigint(20) unsigned NOT NULL,
  `skill_name` varchar(255) NOT NULL,
  `skill_type` enum('technical','soft') NOT NULL DEFAULT 'technical',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resume_skills_resume_id_foreign` (`resume_id`),
  CONSTRAINT `resume_skills_resume_id_foreign` FOREIGN KEY (`resume_id`) REFERENCES `resumes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resume_skills`
--

LOCK TABLES `resume_skills` WRITE;
/*!40000 ALTER TABLE `resume_skills` DISABLE KEYS */;
/*!40000 ALTER TABLE `resume_skills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resumes`
--

DROP TABLE IF EXISTS `resumes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resumes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `job_title` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `linkedin` varchar(255) DEFAULT NULL,
  `github` varchar(255) DEFAULT NULL,
  `portfolio` varchar(255) DEFAULT NULL,
  `summary` text DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resumes_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resumes`
--

LOCK TABLES `resumes` WRITE;
/*!40000 ALTER TABLE `resumes` DISABLE KEYS */;
/*!40000 ALTER TABLE `resumes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_slug_unique_2` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'019f88e5-b1fa-735d-9d8b-19f8bf22d510','member','Member',1,1,'2026-07-22 02:46:22','2026-07-22 02:46:22',NULL);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `saved_jobs`
--

DROP TABLE IF EXISTS `saved_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saved_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `member_id` bigint(20) unsigned NOT NULL,
  `job_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `saved_jobs_member_id_job_id_unique` (`member_id`,`job_id`),
  KEY `saved_jobs_job_id_foreign` (`job_id`),
  CONSTRAINT `saved_jobs_job_id_foreign` FOREIGN KEY (`job_id`) REFERENCES `job_posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `saved_jobs_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `saved_jobs`
--

LOCK TABLES `saved_jobs` WRITE;
/*!40000 ALTER TABLE `saved_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `saved_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('RxCftPq6mFBEGF6A5yrq4Kyqg1rnxi6AVoRaQ6EI',NULL,'127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36','YTozOntzOjY6Il90b2tlbiI7czo0MDoiWEgxZU5LRTdIbHk5M1k4OTVpam4yZWVoclJMQWZKNGRkWk03dnFjZiI7czoxODoiZmxhc2hlcjo6ZW52ZWxvcGVzIjthOjA6e31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19',1787298485);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `site_settings`
--

DROP TABLE IF EXISTS `site_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `enable_email` tinyint(1) NOT NULL DEFAULT 1,
  `enable_whatsapp` tinyint(1) NOT NULL DEFAULT 1,
  `site_email` varchar(255) DEFAULT NULL,
  `site_phone` varchar(255) DEFAULT NULL,
  `dark_logo_path` varchar(255) DEFAULT NULL,
  `light_logo_path` varchar(255) DEFAULT NULL,
  `favicon_path` varchar(255) DEFAULT NULL,
  `facebook_url` varchar(255) DEFAULT NULL,
  `twitter_url` varchar(255) DEFAULT NULL,
  `instagram_url` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `site_name` varchar(255) DEFAULT NULL,
  `site_description` text DEFAULT NULL,
  `timezone` varchar(255) NOT NULL DEFAULT 'UTC',
  `date_format` varchar(255) NOT NULL DEFAULT 'Y-m-d',
  `time_format` varchar(255) NOT NULL DEFAULT 'H:i:s',
  `maintenance_mode` tinyint(1) NOT NULL DEFAULT 0,
  `maintenance_message` text DEFAULT NULL,
  `meta_title` varchar(255) DEFAULT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `site_settings`
--

LOCK TABLES `site_settings` WRITE;
/*!40000 ALTER TABLE `site_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `site_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_documents`
--

DROP TABLE IF EXISTS `staff_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `member_id` bigint(20) unsigned NOT NULL,
  `super_admin_id` bigint(20) unsigned NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  `extension` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_documents`
--

LOCK TABLES `staff_documents` WRITE;
/*!40000 ALTER TABLE `staff_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `stages`
--

DROP TABLE IF EXISTS `stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `task_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `min_days` int(11) NOT NULL,
  `max_days` int(11) NOT NULL,
  `order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_change_stage_date` timestamp NULL DEFAULT NULL,
  `stage_overdue_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `stages`
--

LOCK TABLES `stages` WRITE;
/*!40000 ALTER TABLE `stages` DISABLE KEYS */;
/*!40000 ALTER TABLE `stages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `super_admin_password_logs`
--

DROP TABLE IF EXISTS `super_admin_password_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `super_admin_password_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `role` varchar(255) DEFAULT NULL,
  `new_password` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `super_admin_password_logs`
--

LOCK TABLES `super_admin_password_logs` WRITE;
/*!40000 ALTER TABLE `super_admin_password_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `super_admin_password_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `super_admins`
--

DROP TABLE IF EXISTS `super_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `super_admins` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `uuid` char(36) DEFAULT NULL,
  `roles` longtext DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `whatsapp_phone` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `reset_password_token` varchar(255) DEFAULT NULL,
  `reset_password_token_expires_at` timestamp NULL DEFAULT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `super_admins_username_unique` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `super_admins`
--

LOCK TABLES `super_admins` WRITE;
/*!40000 ALTER TABLE `super_admins` DISABLE KEYS */;
INSERT INTO `super_admins` VALUES (1,NULL,'01991d63-e1ca-7234-929f-a10bdd0d7ac8','[\"admin\"]','Super Admin','superadmin@gmail.com','profile_image/2026_04_28_171453_iIfPpmMfPnKrGsmY.jpg','7733844020','7733844020','$2y$12$ajfCbC9OykzwXh1PZ/LPfOc/JDBsH6e0zY6IFFfb1OVoRaz/hGK9m','f8WAoYxR8sp3ClYkzDaBYo1SmR81D4VB0buShPvyZj0cJMej5uLMt5pAurCJ',NULL,NULL,1,'2025-09-05 17:58:29','2026-04-28 11:44:53');
/*!40000 ALTER TABLE `super_admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_settings`
--

DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `last_updated_by` bigint(20) unsigned DEFAULT NULL COMMENT 'Last Update By User',
  `name` varchar(255) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `extra` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_settings`
--

LOCK TABLES `system_settings` WRITE;
/*!40000 ALTER TABLE `system_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_activity_logs`
--

DROP TABLE IF EXISTS `task_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_activity_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `is_admin` tinyint(4) NOT NULL DEFAULT 0,
  `performed_by` bigint(20) unsigned NOT NULL,
  `action` text DEFAULT NULL,
  `changes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`changes`)),
  `remarks` text DEFAULT NULL,
  `performed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_activity_logs`
--

LOCK TABLES `task_activity_logs` WRITE;
/*!40000 ALTER TABLE `task_activity_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_activity_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_assignments`
--

DROP TABLE IF EXISTS `task_assignments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned NOT NULL,
  `assigned_by` bigint(20) unsigned NOT NULL,
  `assigned_by_type` varchar(255) NOT NULL DEFAULT 'superadmin',
  `is_transferred` tinyint(1) NOT NULL DEFAULT 0,
  `parent_assignment_id` bigint(20) unsigned DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_assignments`
--

LOCK TABLES `task_assignments` WRITE;
/*!40000 ALTER TABLE `task_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_assignments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_comments`
--

DROP TABLE IF EXISTS `task_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_comments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reply_note_id` bigint(20) unsigned DEFAULT NULL,
  `uuid` varchar(255) NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `commented_by` bigint(20) unsigned NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_comments`
--

LOCK TABLES `task_comments` WRITE;
/*!40000 ALTER TABLE `task_comments` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_comments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_documents`
--

DROP TABLE IF EXISTS `task_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_documents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `task_id` bigint(20) unsigned DEFAULT NULL,
  `uploaded_by` bigint(20) unsigned NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `path` varchar(255) NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_documents`
--

LOCK TABLES `task_documents` WRITE;
/*!40000 ALTER TABLE `task_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_instances`
--

DROP TABLE IF EXISTS `task_instances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_instances` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `assigned_to` bigint(20) unsigned NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('pending','in_progress','completed','overdue') NOT NULL DEFAULT 'pending',
  `completed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_instances`
--

LOCK TABLES `task_instances` WRITE;
/*!40000 ALTER TABLE `task_instances` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_instances` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `task_logs`
--

DROP TABLE IF EXISTS `task_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `task_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `task_assignment_id` bigint(20) unsigned NOT NULL,
  `log_date` date NOT NULL,
  `status` enum('pending','in_progress','completed') NOT NULL DEFAULT 'pending',
  `remarks` text DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `task_id` bigint(20) unsigned NOT NULL,
  `performed_by` bigint(20) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `task_logs_task_assignment_id_log_date_unique` (`task_assignment_id`,`log_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `task_logs`
--

LOCK TABLES `task_logs` WRITE;
/*!40000 ALTER TABLE `task_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `task_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `task_type` varchar(255) NOT NULL DEFAULT 'one_time',
  `specific_day` varchar(255) DEFAULT NULL,
  `specific_date` varchar(255) DEFAULT NULL,
  `is_stage` tinyint(1) NOT NULL DEFAULT 0,
  `recurring_type` varchar(255) DEFAULT NULL,
  `recurring_days` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`recurring_days`)),
  `start_from` date DEFAULT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'running',
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `phone` varchar(255) NOT NULL,
  `whatsapp_phone` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL COMMENT 'User location latitude',
  `longitude` decimal(11,8) DEFAULT NULL COMMENT 'User location longitude',
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 1 COMMENT '1-Active, 0-InActive',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vehicles`
--

DROP TABLE IF EXISTS `vehicles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vehicles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `vehicle_id` varchar(255) NOT NULL,
  `vehicle_type` varchar(255) DEFAULT NULL,
  `vehicle_number` varchar(255) NOT NULL,
  `vehicle_name` varchar(255) DEFAULT NULL,
  `brand` varchar(255) DEFAULT NULL,
  `fuel_type` varchar(255) DEFAULT NULL,
  `color` varchar(255) DEFAULT NULL,
  `manufacturing_year` varchar(255) DEFAULT NULL,
  `engine_number` varchar(255) DEFAULT NULL,
  `chassis_number` varchar(255) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_amount` decimal(12,2) DEFAULT NULL,
  `current_km_reading` varchar(255) DEFAULT NULL,
  `vehicle_image` varchar(255) DEFAULT NULL,
  `status` tinyint(4) NOT NULL DEFAULT 0 COMMENT '0 = Active, 1 = Inactive, 2 = Sold',
  `insurance_provider` varchar(255) DEFAULT NULL,
  `policy_number` varchar(255) DEFAULT NULL,
  `insurance_type` varchar(255) DEFAULT NULL,
  `insurance_start_date` date DEFAULT NULL,
  `insurance_end_date` date DEFAULT NULL,
  `insurance_status` tinyint(4) DEFAULT NULL COMMENT '0 = Expired, 1 = Active',
  `puc_certificate_number` varchar(255) DEFAULT NULL,
  `puc_issue_date` date DEFAULT NULL,
  `puc_expiry_date` date DEFAULT NULL,
  `puc_status` tinyint(4) DEFAULT NULL COMMENT '0 = Expired, 1 = Valid',
  `challan_number` varchar(255) DEFAULT NULL,
  `challan_date` date DEFAULT NULL,
  `violation_type` varchar(255) DEFAULT NULL,
  `fine_amount` decimal(10,2) DEFAULT NULL,
  `payment_status` tinyint(4) DEFAULT NULL COMMENT '0 = Unpaid, 1 = Paid',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vehicles_uuid_unique` (`uuid`),
  UNIQUE KEY `vehicles_vehicle_id_unique` (`vehicle_id`),
  UNIQUE KEY `vehicles_vehicle_number_unique` (`vehicle_number`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vehicles`
--

LOCK TABLES `vehicles` WRITE;
/*!40000 ALTER TABLE `vehicles` DISABLE KEYS */;
INSERT INTO `vehicles` VALUES (1,'019f8ef2-7a89-71e7-8418-ab8116c4aba8','VHC-000001','Car','RJ14AU2004','swift','suzuki','Petrol','white','2019','667778889999000002',NULL,'2015-09-09',1199999.96,'50000 km',NULL,0,'nothing....','77474774747','Comprehensive','2025-09-09','2026-09-09',1,'87599988777','2026-08-08','2026-10-09',1,'7874988984','2026-07-09','overspeed',12888.00,NULL,'2026-07-23 06:58:03','2026-07-23 06:58:03',NULL),(2,'019f92c0-eee9-73df-9799-23a67de5e9ac','VHC-000002','Motorcycle','RJ14AU2005','rider','tvs','Petrol','black','2021','667778889999000002','000000','2025-09-08',150000.00,'1000 km',NULL,0,'icici','83838838',NULL,'2025-09-08','2026-09-08',1,'876645566','2025-09-08','2026-09-08',1,'88990022009900','2026-06-05','over speeding',2000.00,NULL,'2026-07-24 00:42:25','2026-07-24 00:42:25',NULL);
/*!40000 ALTER TABLE `vehicles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_logs`
--

DROP TABLE IF EXISTS `whatsapp_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) NOT NULL,
  `member_id` bigint(20) unsigned DEFAULT NULL,
  `phone` varchar(255) NOT NULL,
  `error` longtext NOT NULL,
  `error_message` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL COMMENT 'success, failed',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_logs`
--

LOCK TABLES `whatsapp_logs` WRITE;
/*!40000 ALTER TABLE `whatsapp_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'cadmax_admin'
--

--
-- Dumping routines for database 'cadmax_admin'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-25 13:36:28

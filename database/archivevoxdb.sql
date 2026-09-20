CREATE DATABASE  IF NOT EXISTS `archivevox` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `archivevox`;
-- MySQL dump 10.13  Distrib 8.0.44, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: archivevox
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `asr_log`
--

DROP TABLE IF EXISTS `asr_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `asr_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `filename` varchar(255) NOT NULL,
  `text` text DEFAULT NULL,
  `confidence` decimal(5,4) DEFAULT NULL,
  `language` varchar(10) DEFAULT NULL,
  `model` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_language` (`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asr_log`
--

LOCK TABLES `asr_log` WRITE;
/*!40000 ALTER TABLE `asr_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `asr_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `assessment_result`
--

DROP TABLE IF EXISTS `assessment_result`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_result` (
  `assessment_id` int(11) NOT NULL AUTO_INCREMENT,
  `activity_id` int(11) NOT NULL,
  `total_words` int(11) NOT NULL,
  `words_correct` int(11) NOT NULL,
  `accuracy_percentage` decimal(5,2) DEFAULT NULL,
  `wcpm` decimal(6,2) DEFAULT NULL,
  `reading_time_seconds` int(11) DEFAULT NULL,
  `substitutions` int(11) DEFAULT 0,
  `omissions` int(11) DEFAULT 0,
  `insertions` int(11) DEFAULT 0,
  `repetitions` int(11) DEFAULT 0,
  `self_corrections` int(11) DEFAULT 0,
  `transcript` longtext DEFAULT NULL,
  `reading_level` varchar(50) DEFAULT NULL,
  `teacher_feedback` text DEFAULT NULL,
  `part1_task1_score` int(11) DEFAULT NULL,
  `part1_words_score` int(11) DEFAULT NULL,
  `part1_total_score` int(11) DEFAULT NULL,
  `part1_reading_level` varchar(50) DEFAULT NULL,
  `story_number` int(11) DEFAULT NULL,
  `miscues` int(11) DEFAULT 0,
  `words_read` int(11) DEFAULT 0,
  `minutes` int(11) DEFAULT 0,
  `seconds` int(11) DEFAULT 0,
  `comprehension_score` int(11) DEFAULT NULL,
  `final_reading_level` varchar(50) DEFAULT NULL,
  `observation_level` enum('Level 1','Level 2','Level 3','Level 4') DEFAULT NULL,
  `transcription_language` varchar(20) DEFAULT NULL,
  `pronunciation_score` decimal(5,2) DEFAULT NULL,
  `fluency_score` decimal(5,2) DEFAULT NULL,
  `expression_score` decimal(5,2) DEFAULT NULL,
  `assessed_at` datetime DEFAULT current_timestamp(),
  `confidence_score` decimal(5,4) DEFAULT NULL,
  `word_timings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`word_timings`)),
  PRIMARY KEY (`assessment_id`),
  UNIQUE KEY `uq_assessment_activity` (`activity_id`),
  KEY `idx_assessment_activity` (`activity_id`),
  CONSTRAINT `assessment_result_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `reading_activity` (`activity_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_result`
--

LOCK TABLES `assessment_result` WRITE;
/*!40000 ALTER TABLE `assessment_result` DISABLE KEYS */;
INSERT INTO `assessment_result` VALUES (9,5,24,20,83.33,120.00,10,1,3,0,0,0,'My name is Donny. I am nine years old. I live in London. I have a brother. His name is Jim. Jim is Cybertall.','Frustration',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,'Transitioning Reader','Level 2','auto',NULL,NULL,NULL,'2026-09-12 17:11:33',NULL,NULL),(10,6,21,21,100.00,114.55,11,0,0,0,2,0,'This little boy is in a jeep. His jeep is green. The little boy loves his jeep. He rides and rides.','Independent',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,'Transitioning Reader','Level 2','auto',NULL,NULL,NULL,'2026-09-12 17:41:17',NULL,NULL),(11,7,21,18,85.71,108.00,10,3,0,0,2,0,'This little boy is in a dip. His dip is green. The little boy loves his dip. He rides and rides.','Developing Reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,'Transitioning Reader','Level 2','auto',NULL,NULL,NULL,'2026-09-13 11:52:04',NULL,NULL);
/*!40000 ALTER TABLE `assessment_result` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `class`
--

DROP TABLE IF EXISTS `class`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `class` (
  `class_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `grade_level` enum('Grade 2','Grade 3') DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`class_id`),
  KEY `teacher_id` (`teacher_id`),
  CONSTRAINT `class_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class`
--

LOCK TABLES `class` WRITE;
/*!40000 ALTER TABLE `class` DISABLE KEYS */;
INSERT INTO `class` VALUES (1,1,'Grade 2','Section A','2025-2026'),(2,1,'Grade 2','Section B','2025-2026'),(30,6,'Grade 3','Apitong',NULL);
/*!40000 ALTER TABLE `class` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `crla_criteria`
--

DROP TABLE IF EXISTS `crla_criteria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `crla_criteria` (
  `criteria_id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_level` enum('Grade 1','Grade 2','Grade 3') DEFAULT NULL,
  `assessment_part` enum('Part 1','Part 2') DEFAULT NULL,
  `minimum_score` int(11) DEFAULT NULL,
  `maximum_score` int(11) DEFAULT NULL,
  `minimum_accuracy` decimal(5,2) DEFAULT NULL,
  `maximum_accuracy` decimal(5,2) DEFAULT NULL,
  `minimum_questions` int(11) DEFAULT NULL,
  `maximum_questions` int(11) DEFAULT NULL,
  `reading_level` varchar(50) DEFAULT NULL,
  `observation_level` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`criteria_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `crla_criteria`
--

LOCK TABLES `crla_criteria` WRITE;
/*!40000 ALTER TABLE `crla_criteria` DISABLE KEYS */;
INSERT INTO `crla_criteria` VALUES (1,'Grade 3','Part 1',0,10,NULL,NULL,NULL,NULL,'Low Emerging Reader',NULL),(2,'Grade 3','Part 2',NULL,NULL,NULL,24.99,1,1,'High Emerging Reader','Level 1'),(3,'Grade 3','Part 2',NULL,NULL,26.00,50.00,2,3,'Developing Reader','Level 2'),(4,'Grade 3','Part 2',NULL,NULL,51.00,75.00,4,5,'Transitioning Reader','Level 3'),(5,'Grade 3','Part 2',NULL,NULL,76.00,100.00,6,7,'Reading At Grade Level','Level 4');
/*!40000 ALTER TABLE `crla_criteria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_attempts` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` datetime DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`attempt_id`),
  KEY `idx_ip_time` (`ip_address`,`attempt_time`),
  KEY `fk_attempt_user` (`user_id`),
  CONSTRAINT `fk_attempt_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,NULL,'::1','2026-08-06 17:40:59',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(2,NULL,'::1','2026-08-06 17:41:06',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(3,NULL,'::1','2026-08-06 17:41:23',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(4,NULL,'::1','2026-08-06 17:41:27',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(5,NULL,'::1','2026-08-06 17:41:31',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(6,3,'::1','2026-08-06 17:47:13',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(7,3,'::1','2026-08-06 17:47:45',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(8,NULL,'::1','2026-08-06 17:49:33',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(9,NULL,'::1','2026-08-06 18:11:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(10,NULL,'::1','2026-08-06 18:13:00',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(11,NULL,'::1','2026-08-06 19:32:39',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(12,2,'::1','2026-08-06 19:35:19',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(13,NULL,'::1','2026-08-06 19:37:01',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(14,NULL,'::1','2026-08-06 19:38:39',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(15,1,'::1','2026-08-06 19:43:24',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(16,2,'::1','2026-08-06 19:43:24',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(17,3,'::1','2026-08-06 19:43:24',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(18,4,'::1','2026-08-06 19:43:25',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(19,5,'::1','2026-08-06 19:43:25',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(20,NULL,'::1','2026-08-06 19:49:14',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(21,NULL,'::1','2026-08-06 19:49:44',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(22,3,'::1','2026-08-06 19:56:46',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(23,NULL,'::1','2026-08-06 20:05:47',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(24,3,'::1','2026-08-06 20:06:06',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(25,NULL,'::1','2026-08-06 21:39:19',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(26,3,'::1','2026-08-06 21:41:30',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(27,2,'::1','2026-08-06 22:04:36',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(28,4,'::1','2026-08-06 22:05:36',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(29,3,'::1','2026-08-08 10:31:50',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(30,3,'::1','2026-08-08 10:31:59',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(31,2,'::1','2026-08-08 10:42:15',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(32,3,'::1','2026-08-08 10:52:15',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(33,4,'::1','2026-08-08 11:06:10',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(34,3,'::1','2026-08-08 11:14:16',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(35,4,'::1','2026-08-08 11:23:58',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(36,4,'::1','2026-08-08 13:17:24',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(37,4,'::1','2026-08-08 16:52:41',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(38,4,'::1','2026-08-08 20:00:02',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(39,2,'::1','2026-08-08 20:00:31',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(40,2,'::1','2026-08-08 20:07:23',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(41,4,'::1','2026-08-08 20:07:58',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(42,4,'::1','2026-08-08 20:08:06',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(43,4,'::1','2026-08-08 20:08:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(44,2,'::1','2026-08-08 20:09:08',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(45,2,'::1','2026-08-08 20:20:49',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(46,3,'::1','2026-08-08 20:27:51',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(47,3,'::1','2026-08-10 03:21:09',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(48,3,'::1','2026-08-10 03:28:56',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(49,3,'::1','2026-08-10 11:08:17',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(50,2,'::1','2026-08-10 11:50:14',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(51,4,'::1','2026-08-10 11:50:45',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(52,4,'::1','2026-08-10 12:52:35',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(53,4,'::1','2026-08-10 12:53:58',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(54,2,'::1','2026-08-10 13:11:06',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(55,2,'::1','2026-08-10 13:44:25',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(56,4,'::1','2026-08-10 14:01:12',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(57,4,'::1','2026-08-10 14:58:06',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(58,4,'::1','2026-08-12 13:37:32',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(59,4,'::1','2026-08-12 14:14:45',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(60,4,'::1','2026-08-13 17:23:55',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(61,2,'::1','2026-08-13 17:26:36',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(62,2,'::1','2026-08-13 20:29:24',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(63,2,'::1','2026-08-15 22:06:30',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(64,4,'::1','2026-08-15 22:44:02',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(65,2,'::1','2026-08-16 15:23:55',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(66,2,'::1','2026-08-16 15:27:51',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(67,4,'::1','2026-08-16 19:54:01',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(68,2,'::1','2026-08-16 20:36:34',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(69,2,'::1','2026-08-16 20:45:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(70,4,'::1','2026-08-16 21:06:22',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(71,4,'::1','2026-08-17 18:58:15',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(72,2,'::1','2026-08-17 19:05:50',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(73,2,'::1','2026-08-17 19:06:59',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(74,4,'::1','2026-08-17 19:07:14',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(75,3,'::1','2026-08-17 19:12:24',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(76,4,'::1','2026-08-17 19:14:55',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(77,4,'::1','2026-08-17 19:15:12',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(78,2,'::1','2026-08-17 19:24:16',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(79,2,'::1','2026-08-17 19:29:01',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(80,3,'::1','2026-08-17 19:29:13',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(81,3,'::1','2026-08-25 21:13:05',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(82,3,'::1','2026-08-29 18:39:54',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(83,3,'::1','2026-08-30 23:36:15',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(84,3,'::1','2026-08-31 11:26:19',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(85,2,'::1','2026-08-31 11:40:54',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(86,NULL,'::1','2026-08-31 17:15:40',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(87,NULL,'::1','2026-08-31 17:16:39',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(88,NULL,'::1','2026-08-31 17:16:57',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(89,NULL,'::1','2026-08-31 17:17:58',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(90,NULL,'::1','2026-08-31 17:18:21',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(91,16,'::1','2026-08-31 17:23:37',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(92,16,'::1','2026-08-31 19:11:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(93,16,'::1','2026-09-04 01:26:27',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(94,16,'::1','2026-09-04 01:27:07',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(95,3,'::1','2026-09-04 01:31:58',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(96,3,'::1','2026-09-04 01:32:13',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(97,16,'::1','2026-09-04 01:33:29',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(98,17,'::1','2026-09-04 23:39:54',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(99,16,'::1','2026-09-05 00:26:52',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(100,17,'::1','2026-09-05 00:37:46',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(101,3,'::1','2026-09-05 00:43:38',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(102,17,'::1','2026-09-06 14:55:21',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(103,3,'::1','2026-09-06 14:57:13',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(104,17,'::1','2026-09-06 15:50:58',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(105,3,'::1','2026-09-06 16:10:07',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(106,17,'::1','2026-09-06 16:10:45',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(107,17,'::1','2026-09-06 16:11:41',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(108,3,'::1','2026-09-06 22:35:15',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(109,17,'::1','2026-09-07 01:01:52',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(110,3,'::1','2026-09-07 01:06:06',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(111,17,'::1','2026-09-07 01:14:30',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(112,17,'::1','2026-09-07 01:58:40',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(113,3,'::1','2026-09-07 19:50:21',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(114,17,'::1','2026-09-07 20:37:38',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(115,3,'::1','2026-09-08 01:25:55',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(116,17,'::1','2026-09-08 01:41:43',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(117,3,'::1','2026-09-08 01:42:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(118,4,'::1','2026-09-08 01:43:15',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(119,4,'::1','2026-09-08 01:44:38',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(120,17,'::1','2026-09-08 01:59:07',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(121,20,'::1','2026-09-08 02:00:18',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(122,4,'::1','2026-09-08 02:02:24',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(123,4,'::1','2026-09-08 18:07:19',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(124,4,'::1','2026-09-08 18:07:22',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(125,4,'::1','2026-09-08 18:10:12',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(126,4,'::1','2026-09-08 18:10:13',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(127,4,'::1','2026-09-08 18:20:45',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(128,4,'::1','2026-09-08 18:25:02',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(129,2,'::1','2026-09-08 18:35:33',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(130,4,'::1','2026-09-08 19:06:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(131,17,'::1','2026-09-08 19:13:45',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(132,3,'::1','2026-09-08 19:14:35',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(133,17,'::1','2026-09-09 00:33:01',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(134,4,'::1','2026-09-09 00:34:26',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(135,2,'::1','2026-09-09 00:35:47',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(136,2,'::1','2026-09-09 01:39:22',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(137,2,'::1','2026-09-09 01:41:44',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(138,2,'::1','2026-09-09 01:42:48',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(139,4,'::1','2026-09-09 01:43:11',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(140,2,'::1','2026-09-09 01:46:19',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(141,3,'::1','2026-09-09 01:55:56',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(142,3,'::1','2026-09-09 01:56:18',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(143,17,'::1','2026-09-09 01:56:59',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(144,3,'::1','2026-09-09 01:58:28',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(145,16,'::1','2026-09-09 01:58:43',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(146,16,'::1','2026-09-09 02:06:16',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(147,24,'::1','2026-09-09 02:31:17',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(148,24,'::1','2026-09-09 02:44:28',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(149,16,'::1','2026-09-09 02:57:17',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(150,NULL,'::1','2026-09-10 22:26:59',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(151,3,'::1','2026-09-10 22:27:28',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(152,4,'::1','2026-09-11 00:18:12',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(153,4,'::1','2026-09-11 00:18:38',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(154,4,'::1','2026-09-11 00:24:12',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(155,3,'::1','2026-09-11 00:25:07',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(156,2,'::1','2026-09-11 00:28:13',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(157,3,'::1','2026-09-11 00:30:50',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(158,2,'::1','2026-09-11 02:34:36',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(159,NULL,'::1','2026-09-11 02:37:14',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(160,NULL,'::1','2026-09-11 02:37:28',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(161,16,'::1','2026-09-11 02:38:03',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(162,NULL,'::1','2026-09-11 02:45:42',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(163,16,'::1','2026-09-11 02:45:45',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(164,16,'::1','2026-09-11 03:01:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(165,NULL,'::1','2026-09-11 03:02:15',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(166,NULL,'::1','2026-09-11 03:02:23',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(167,NULL,'::1','2026-09-11 03:02:30',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(168,16,'::1','2026-09-11 03:07:27',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(169,16,'::1','2026-09-11 03:07:32',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(170,NULL,'::1','2026-09-11 03:08:09',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(171,NULL,'::1','2026-09-11 03:08:20',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(172,16,'::1','2026-09-11 03:11:42',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(173,16,'::1','2026-09-11 03:11:48',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(174,48,'::1','2026-09-11 03:16:25',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(175,48,'::1','2026-09-11 03:19:04',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(176,46,'::1','2026-09-11 03:59:03',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(177,16,'::1','2026-09-11 04:21:11',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(178,48,'::1','2026-09-12 12:47:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(179,2,'::1','2026-09-12 14:42:56',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(180,16,'::1','2026-09-12 16:47:31',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(181,16,'::1','2026-09-12 17:09:29',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(182,48,'::1','2026-09-12 17:09:35',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(183,16,'::1','2026-09-12 17:12:18',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(184,46,'::1','2026-09-12 17:40:11',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(185,16,'::1','2026-09-12 17:42:20',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(186,2,'::1','2026-09-13 01:48:12',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(187,16,'::1','2026-09-13 01:57:03',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(188,46,'::1','2026-09-13 01:57:13',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(189,46,'::1','2026-09-13 02:10:02',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(190,16,'::1','2026-09-13 02:31:47',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(191,46,'::1','2026-09-13 02:42:40',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(192,16,'::1','2026-09-13 03:32:30',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(193,46,'::1','2026-09-13 03:45:46',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(194,16,'::1','2026-09-13 03:59:47',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(195,16,'::1','2026-09-13 04:33:36',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(196,16,'::1','2026-09-13 11:49:38',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(197,47,'::1','2026-09-13 11:50:05',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(198,16,'::1','2026-09-13 11:53:20',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(199,16,'::1','2026-09-13 14:49:23',1,'Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1'),(200,16,'::1','2026-09-14 01:35:00',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0');
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_logs`
--

DROP TABLE IF EXISTS `login_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `login_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `login_time` datetime DEFAULT current_timestamp(),
  `logout_time` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `device_info` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`log_id`),
  KEY `idx_login_user` (`user_id`),
  CONSTRAINT `login_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=174 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_logs`
--

LOCK TABLES `login_logs` WRITE;
/*!40000 ALTER TABLE `login_logs` DISABLE KEYS */;
INSERT INTO `login_logs` VALUES (1,3,'2026-08-06 17:47:13',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(2,3,'2026-08-06 17:47:45',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(7,2,'2026-08-06 19:35:19',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(10,2,'2026-08-06 19:43:24',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(11,3,'2026-08-06 19:43:24',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(14,3,'2026-08-06 19:56:46',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(16,3,'2026-08-06 20:06:06',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(18,3,'2026-08-06 21:41:30',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(19,2,'2026-08-06 22:04:36',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(20,4,'2026-08-06 22:05:36',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(21,3,'2026-08-08 10:31:59',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(22,2,'2026-08-08 10:42:15',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(23,3,'2026-08-08 10:52:15',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(24,4,'2026-08-08 11:06:10',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(25,3,'2026-08-08 11:14:16',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(26,4,'2026-08-08 11:23:58',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(27,4,'2026-08-08 13:17:24',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(28,4,'2026-08-08 16:52:41',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(29,4,'2026-08-08 20:00:02',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(30,2,'2026-08-08 20:00:31',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(31,2,'2026-08-08 20:07:23',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(32,4,'2026-08-08 20:07:58',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(33,4,'2026-08-08 20:08:06',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(34,4,'2026-08-08 20:08:53',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(35,2,'2026-08-08 20:09:08',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(36,2,'2026-08-08 20:20:49',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(37,3,'2026-08-08 20:27:51',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(38,3,'2026-08-10 03:21:09',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(39,3,'2026-08-10 03:28:56',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(40,3,'2026-08-10 11:08:17',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(41,2,'2026-08-10 11:50:14',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(42,4,'2026-08-10 11:50:45',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(43,4,'2026-08-10 12:52:35',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(44,4,'2026-08-10 12:53:58',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(45,2,'2026-08-10 13:11:06',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(46,2,'2026-08-10 13:44:25',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(47,4,'2026-08-10 14:01:12',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(48,4,'2026-08-10 14:58:06',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(49,4,'2026-08-12 13:37:32',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(50,4,'2026-08-12 14:14:45',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(51,4,'2026-08-13 17:23:55',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(52,2,'2026-08-13 17:26:36',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(53,2,'2026-08-13 20:29:24',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(54,2,'2026-08-15 22:06:30',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(55,4,'2026-08-15 22:44:02',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(56,2,'2026-08-16 15:23:55',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(57,2,'2026-08-16 15:27:51',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(58,4,'2026-08-16 19:54:01',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(59,2,'2026-08-16 20:36:34',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(60,2,'2026-08-16 20:45:53',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(61,4,'2026-08-16 21:06:22',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(62,4,'2026-08-17 18:58:15',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(63,2,'2026-08-17 19:05:50',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(64,2,'2026-08-17 19:06:59',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(65,4,'2026-08-17 19:07:14',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(66,3,'2026-08-17 19:12:24',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(67,4,'2026-08-17 19:14:55',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(68,4,'2026-08-17 19:15:12',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(69,2,'2026-08-17 19:24:16',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(70,2,'2026-08-17 19:29:01',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(71,3,'2026-08-17 19:29:13',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(72,3,'2026-08-25 21:13:05',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(73,3,'2026-08-29 18:39:54',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(74,3,'2026-08-30 23:36:15',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(75,3,'2026-08-31 11:26:19',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(76,2,'2026-08-31 11:40:54',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(77,16,'2026-08-31 17:23:37',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(78,16,'2026-08-31 19:11:53',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(79,16,'2026-09-04 01:26:27',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(80,16,'2026-09-04 01:27:07',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(81,3,'2026-09-04 01:31:58',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(82,3,'2026-09-04 01:32:13',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(83,16,'2026-09-04 01:33:29',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(84,17,'2026-09-04 23:39:54',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(85,16,'2026-09-05 00:26:52',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(86,17,'2026-09-05 00:37:46',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(87,3,'2026-09-05 00:43:38',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(88,17,'2026-09-06 14:55:21',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(89,3,'2026-09-06 14:57:13',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(90,17,'2026-09-06 15:50:58',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(91,3,'2026-09-06 16:10:07',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(92,17,'2026-09-06 16:10:45',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(93,17,'2026-09-06 16:11:41',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(94,3,'2026-09-06 22:35:15',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(95,17,'2026-09-07 01:01:52',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(96,3,'2026-09-07 01:06:06',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(97,17,'2026-09-07 01:14:30',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(98,17,'2026-09-07 01:58:40',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(99,3,'2026-09-07 19:50:21',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(100,17,'2026-09-07 20:37:38',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(101,3,'2026-09-08 01:25:55',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(102,17,'2026-09-08 01:41:43',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(103,3,'2026-09-08 01:42:53',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(104,4,'2026-09-08 01:43:15',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(105,4,'2026-09-08 01:44:38',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(106,17,'2026-09-08 01:59:07',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(107,20,'2026-09-08 02:00:18',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(108,4,'2026-09-08 02:02:24',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(109,4,'2026-09-08 18:07:19',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(110,4,'2026-09-08 18:07:22',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(111,4,'2026-09-08 18:10:12',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(112,4,'2026-09-08 18:10:13',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(113,4,'2026-09-08 18:20:45',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(114,4,'2026-09-08 18:25:02',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(115,2,'2026-09-08 18:35:33',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(116,4,'2026-09-08 19:06:53',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(117,17,'2026-09-08 19:13:45',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(118,3,'2026-09-08 19:14:35',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(119,17,'2026-09-09 00:33:01',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(120,4,'2026-09-09 00:34:26',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(121,2,'2026-09-09 00:35:47',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(122,2,'2026-09-09 01:39:22',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(123,2,'2026-09-09 01:41:44',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(124,2,'2026-09-09 01:42:48',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(125,4,'2026-09-09 01:43:11',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(126,2,'2026-09-09 01:46:19',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(127,3,'2026-09-09 01:55:56',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(128,3,'2026-09-09 01:56:18',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(129,17,'2026-09-09 01:56:59',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(130,3,'2026-09-09 01:58:28',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(131,16,'2026-09-09 01:58:43',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(132,16,'2026-09-09 02:06:16',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(133,24,'2026-09-09 02:31:17',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(134,24,'2026-09-09 02:44:28',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(135,16,'2026-09-09 02:57:17',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(136,3,'2026-09-10 22:27:28',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(137,4,'2026-09-11 00:24:12',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(138,3,'2026-09-11 00:25:07',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(139,2,'2026-09-11 00:28:13',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(140,3,'2026-09-11 00:30:50',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(141,2,'2026-09-11 02:34:36',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(142,16,'2026-09-11 02:38:03',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(143,16,'2026-09-11 02:45:45',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(144,16,'2026-09-11 03:01:53',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(145,16,'2026-09-11 03:07:32',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(146,16,'2026-09-11 03:11:48',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(147,48,'2026-09-11 03:16:25',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(148,48,'2026-09-11 03:19:04',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(149,46,'2026-09-11 03:59:03',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(150,16,'2026-09-11 04:21:11',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(151,48,'2026-09-12 12:47:53',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(152,2,'2026-09-12 14:42:56',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(153,16,'2026-09-12 16:47:31',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(154,16,'2026-09-12 17:09:29',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(155,48,'2026-09-12 17:09:35',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(156,16,'2026-09-12 17:12:18',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(157,46,'2026-09-12 17:40:11',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(158,16,'2026-09-12 17:42:20',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(159,2,'2026-09-13 01:48:12',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(160,16,'2026-09-13 01:57:03',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(161,46,'2026-09-13 01:57:13',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(162,46,'2026-09-13 02:10:02',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(163,16,'2026-09-13 02:31:47',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(164,46,'2026-09-13 02:42:40',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(165,16,'2026-09-13 03:32:30',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(166,46,'2026-09-13 03:45:46',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(167,16,'2026-09-13 03:59:47',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(168,16,'2026-09-13 04:33:36',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(169,16,'2026-09-13 11:49:38',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(170,47,'2026-09-13 11:50:05',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(171,16,'2026-09-13 11:53:20',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0'),(172,16,'2026-09-13 14:49:23',NULL,'::1','Mozilla/5.0 (iPhone; CPU iPhone OS 18_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.5 Mobile/15E148 Safari/604.1'),(173,16,'2026-09-14 01:35:00',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0');
/*!40000 ALTER TABLE `login_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `parent`
--

DROP TABLE IF EXISTS `parent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `parent` (
  `parent_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`parent_id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `parent_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `parent`
--

LOCK TABLES `parent` WRITE;
/*!40000 ALTER TABLE `parent` DISABLE KEYS */;
/*!40000 ALTER TABLE `parent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `principal`
--

DROP TABLE IF EXISTS `principal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `principal` (
  `principal_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `employee_no` varchar(30) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`principal_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `employee_no` (`employee_no`),
  CONSTRAINT `principal_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `principal`
--

LOCK TABLES `principal` WRITE;
/*!40000 ALTER TABLE `principal` DISABLE KEYS */;
INSERT INTO `principal` VALUES (1,1,'P001','Maria',NULL,'Santos','principal@school.edu.ph',NULL);
/*!40000 ALTER TABLE `principal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `progress_record`
--

DROP TABLE IF EXISTS `progress_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `progress_record` (
  `progress_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `performance_level` varchar(50) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `record_date` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`progress_id`),
  KEY `assessment_id` (`assessment_id`),
  KEY `idx_progress_student` (`student_id`),
  KEY `idx_progress_teacher` (`teacher_id`),
  CONSTRAINT `progress_record_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `progress_record_ibfk_2` FOREIGN KEY (`assessment_id`) REFERENCES `assessment_result` (`assessment_id`) ON DELETE CASCADE,
  CONSTRAINT `progress_record_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `progress_record`
--

LOCK TABLES `progress_record` WRITE;
/*!40000 ALTER TABLE `progress_record` DISABLE KEYS */;
/*!40000 ALTER TABLE `progress_record` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz`
--

DROP TABLE IF EXISTS `quiz`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz` (
  `quiz_id` int(11) NOT NULL AUTO_INCREMENT,
  `material_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `instructions` text DEFAULT NULL,
  `total_questions` int(11) unsigned NOT NULL DEFAULT 5,
  `status` enum('draft','active','archived') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`quiz_id`),
  UNIQUE KEY `uq_quiz_material` (`material_id`),
  KEY `idx_quiz_status` (`status`),
  CONSTRAINT `fk_quiz_material` FOREIGN KEY (`material_id`) REFERENCES `reading_material` (`material_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz`
--

LOCK TABLES `quiz` WRITE;
/*!40000 ALTER TABLE `quiz` DISABLE KEYS */;
INSERT INTO `quiz` VALUES (2,3,'Comprehension Quiz',NULL,5,'active','2026-08-22 19:55:57','2026-08-22 19:55:57'),(5,41,'Comprehension Quiz',NULL,5,'active','2026-09-06 23:06:26','2026-09-06 23:06:26'),(6,43,'Comprehension Quiz',NULL,5,'active','2026-09-09 03:21:50','2026-09-09 03:21:50'),(7,47,'Comprehension Quiz',NULL,5,'active','2026-09-11 01:45:51','2026-09-11 01:45:51');
/*!40000 ALTER TABLE `quiz` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_answer`
--

DROP TABLE IF EXISTS `quiz_answer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_answer` (
  `answer_id` int(11) NOT NULL AUTO_INCREMENT,
  `attempt_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `selected_choice_id` int(11) DEFAULT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  `answered_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`answer_id`),
  UNIQUE KEY `uq_attempt_question` (`attempt_id`,`question_id`),
  KEY `idx_answer_attempt` (`attempt_id`),
  KEY `idx_answer_question` (`question_id`),
  KEY `idx_answer_choice` (`selected_choice_id`),
  CONSTRAINT `fk_answer_attempt` FOREIGN KEY (`attempt_id`) REFERENCES `quiz_attempt` (`attempt_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_answer_choice` FOREIGN KEY (`selected_choice_id`) REFERENCES `quiz_choice` (`choice_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_answer_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_question` (`question_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_answer`
--

LOCK TABLES `quiz_answer` WRITE;
/*!40000 ALTER TABLE `quiz_answer` DISABLE KEYS */;
INSERT INTO `quiz_answer` VALUES (16,5,16,61,1,'2026-09-12 17:11:47'),(17,5,17,68,1,'2026-09-12 17:11:47'),(18,5,18,71,1,'2026-09-12 17:11:47'),(19,5,19,75,1,'2026-09-12 17:11:47'),(20,5,20,79,1,'2026-09-12 17:11:47'),(21,6,11,41,1,'2026-09-12 17:41:38'),(22,6,12,46,1,'2026-09-12 17:41:38'),(23,6,13,51,1,'2026-09-12 17:41:38'),(24,6,14,56,1,'2026-09-12 17:41:38'),(25,6,15,57,1,'2026-09-12 17:41:38'),(26,7,11,41,1,'2026-09-13 11:52:46'),(27,7,12,46,1,'2026-09-13 11:52:46'),(28,7,13,51,1,'2026-09-13 11:52:46'),(29,7,14,56,1,'2026-09-13 11:52:46'),(30,7,15,57,1,'2026-09-13 11:52:46');
/*!40000 ALTER TABLE `quiz_answer` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_attempt`
--

DROP TABLE IF EXISTS `quiz_attempt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_attempt` (
  `attempt_id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assignment_material_id` int(11) DEFAULT NULL,
  `activity_id` int(11) DEFAULT NULL,
  `score` int(11) unsigned NOT NULL DEFAULT 0,
  `total_questions` int(11) unsigned NOT NULL DEFAULT 5,
  `percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `started_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `status` enum('in_progress','completed','abandoned') NOT NULL DEFAULT 'in_progress',
  PRIMARY KEY (`attempt_id`),
  KEY `idx_quiz_attempt_quiz` (`quiz_id`),
  KEY `idx_quiz_attempt_student` (`student_id`),
  KEY `idx_quiz_attempt_assignment_material` (`assignment_material_id`),
  KEY `idx_quiz_attempt_activity` (`activity_id`),
  KEY `idx_quiz_attempt_status` (`status`),
  CONSTRAINT `fk_quiz_attempt_activity` FOREIGN KEY (`activity_id`) REFERENCES `reading_activity` (`activity_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_quiz_attempt_assignment_material` FOREIGN KEY (`assignment_material_id`) REFERENCES `reading_assignment_material` (`assignment_material_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_quiz_attempt_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quiz` (`quiz_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_quiz_attempt_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_attempt`
--

LOCK TABLES `quiz_attempt` WRITE;
/*!40000 ALTER TABLE `quiz_attempt` DISABLE KEYS */;
INSERT INTO `quiz_attempt` VALUES (5,7,111,7,5,5,5,100.00,'2026-09-12 17:11:38','2026-09-12 17:11:47','completed'),(6,6,109,8,6,5,5,100.00,'2026-09-12 17:41:23','2026-09-12 17:41:38','completed'),(7,6,110,8,7,5,5,100.00,'2026-09-13 11:52:33','2026-09-13 11:52:46','completed');
/*!40000 ALTER TABLE `quiz_attempt` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_choice`
--

DROP TABLE IF EXISTS `quiz_choice`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_choice` (
  `choice_id` int(11) NOT NULL AUTO_INCREMENT,
  `question_id` int(11) NOT NULL,
  `choice_label` char(1) NOT NULL,
  `choice_text` text NOT NULL,
  `is_correct` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`choice_id`),
  UNIQUE KEY `uq_question_choice_label` (`question_id`,`choice_label`),
  KEY `idx_choice_question` (`question_id`),
  CONSTRAINT `fk_choice_question` FOREIGN KEY (`question_id`) REFERENCES `quiz_question` (`question_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_choice`
--

LOCK TABLES `quiz_choice` WRITE;
/*!40000 ALTER TABLE `quiz_choice` DISABLE KEYS */;
INSERT INTO `quiz_choice` VALUES (1,1,'A','Juan',1),(2,1,'B','Pedro',0),(3,1,'C','Maria',0),(4,1,'D','Ana',0),(5,2,'A','Katamaran',0),(6,2,'B','Kabaitan',1),(7,2,'C','Galit',0),(8,2,'D','Takot',0),(9,3,'A','Tumulong sa iba',1),(10,3,'B','Umalis sa bahay',0),(11,3,'C','Natutulog buong araw',0),(12,3,'D','Nakipag-away',0),(13,4,'A','Maging matulungin',1),(14,4,'B','Huwag makinig',0),(15,4,'C','Iwasang tumulong',0),(16,4,'D','Laging magalit',0),(17,5,'A','Dahil nakatutulong ito sa komunidad',1),(18,5,'B','Dahil masaya ang makipag-away',0),(19,5,'C','Dahil hindi kailangang makinig',0),(20,5,'D','Dahil mas madaling umiwas',0),(21,6,'A','John',0),(22,6,'B','George',0),(23,6,'C','Thomas',1),(24,6,'D','Shin',0),(25,7,'A','10',0),(26,7,'B','5',1),(27,7,'C','3',0),(28,7,'D','9',0),(29,8,'A','Cricket',0),(30,8,'B','Hockey',0),(31,8,'C','Football',1),(32,8,'D','Basketball',0),(33,9,'A','Billy',1),(34,9,'B','Mother',0),(35,9,'C','Father',0),(36,9,'D','Sister',0),(37,10,'A','Siblings',1),(38,10,'B','Cousins',0),(39,10,'C','Friends',0),(40,10,'D','Classmates',0),(41,11,'A','The little boy',1),(42,11,'B','The teacher',0),(43,11,'C','The little girl',0),(44,11,'D','The father',0),(45,12,'A','Red',0),(46,12,'B','Green',1),(47,12,'C','Yellow',0),(48,12,'D','Black',0),(49,13,'A','Bike',0),(50,13,'B','Airplane',0),(51,13,'C','Jeep',1),(52,13,'D','Trike',0),(53,14,'A','A man',0),(54,14,'B','A grandpa',0),(55,14,'C','The little girl',0),(56,14,'D','The little boy',1),(57,15,'A','1',1),(58,15,'B','3',0),(59,15,'C','2',0),(60,15,'D','4',0),(61,16,'A','Johnny',1),(62,16,'B','Benny',0),(63,16,'C','John',0),(64,16,'D','Cody',0),(65,17,'A','10',0),(66,17,'B','7',0),(67,17,'C','6',0),(68,17,'D','9',1),(69,18,'A','Canada',0),(70,18,'B','Florida',0),(71,18,'C','London',1),(72,18,'D','Hollywood',0),(73,19,'A','Jay',0),(74,19,'B','Jack',0),(75,19,'C','Jim',1),(76,19,'D','Jill',0),(77,20,'A','4',0),(78,20,'B','6',0),(79,20,'C','5',1),(80,20,'D','7',0);
/*!40000 ALTER TABLE `quiz_choice` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quiz_question`
--

DROP TABLE IF EXISTS `quiz_question`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quiz_question` (
  `question_id` int(11) NOT NULL AUTO_INCREMENT,
  `quiz_id` int(11) NOT NULL,
  `question_number` int(11) unsigned NOT NULL,
  `question_text` text NOT NULL,
  PRIMARY KEY (`question_id`),
  UNIQUE KEY `uq_quiz_question_number` (`quiz_id`,`question_number`),
  KEY `idx_question_quiz` (`quiz_id`),
  CONSTRAINT `fk_question_quiz` FOREIGN KEY (`quiz_id`) REFERENCES `quiz` (`quiz_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quiz_question`
--

LOCK TABLES `quiz_question` WRITE;
/*!40000 ALTER TABLE `quiz_question` DISABLE KEYS */;
INSERT INTO `quiz_question` VALUES (1,2,1,'Sino ang pangunahing tauhan sa kuwento?'),(2,2,2,'Ano ang ipinakita ng bata?'),(3,2,3,'Ano ang ginawa ng bata?'),(4,2,4,'Ano ang magandang aral sa kuwento?'),(5,2,5,'Bakit mahalagang tumulong sa kapwa?'),(6,5,1,'What is the name of Billy\'s brother?'),(7,5,2,'His brother is how many years old?'),(8,5,3,'Which sport does he love to play?'),(9,5,4,'Who helps him in his studies?'),(10,5,5,'Billy and Thomas are _____?'),(11,6,1,'Who rides and rides the jeep?'),(12,6,2,'What is the color of the jeep?'),(13,6,3,'What is the little boy riding?'),(14,6,4,'Who loves to ride the jeep?'),(15,6,5,'How many is in the jeep?'),(16,7,1,'What is the name of the boy?'),(17,7,2,'How many years old is the boy?'),(18,7,3,'Where does the boy live?'),(19,7,4,'What is his younger brother\'s name?'),(20,7,5,'How many years old is his younger brother?');
/*!40000 ALTER TABLE `quiz_question` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reading_activity`
--

DROP TABLE IF EXISTS `reading_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_activity` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `assignment_material_id` int(11) DEFAULT NULL,
  `activity_date` datetime DEFAULT current_timestamp(),
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `audio_filename` varchar(255) DEFAULT NULL,
  `audio_path` varchar(255) DEFAULT NULL,
  `duration_seconds` int(11) DEFAULT NULL,
  `attempt_number` int(11) DEFAULT 1,
  `activity_status` enum('Pending','Processing','Completed') DEFAULT 'Pending',
  PRIMARY KEY (`activity_id`),
  KEY `idx_activity_student` (`student_id`),
  KEY `idx_activity_material` (`material_id`),
  KEY `idx_reading_activity_assignment_material` (`assignment_material_id`),
  CONSTRAINT `fk_reading_activity_assignment_material` FOREIGN KEY (`assignment_material_id`) REFERENCES `reading_assignment_material` (`assignment_material_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `reading_activity_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `reading_activity_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `reading_material` (`material_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reading_activity`
--

LOCK TABLES `reading_activity` WRITE;
/*!40000 ALTER TABLE `reading_activity` DISABLE KEYS */;
INSERT INTO `reading_activity` VALUES (1,111,47,NULL,'2026-09-12 13:16:40','2026-09-12 13:16:40','2026-09-12 13:17:26','1789190200_111_reading.webm','uploads/audio/1789190200_111_reading.webm',10,1,'Completed'),(2,111,47,NULL,'2026-09-12 13:18:48','2026-09-12 13:18:48','2026-09-12 13:19:56','1789190328_111_reading.webm','uploads/audio/1789190328_111_reading.webm',12,1,'Completed'),(3,111,47,NULL,'2026-09-12 13:34:58','2026-09-12 13:34:58','2026-09-12 13:35:51','1789191298_111_reading.webm','uploads/audio/1789191298_111_reading.webm',10,1,'Completed'),(4,111,47,NULL,'2026-09-12 14:02:56','2026-09-12 14:02:56','2026-09-12 14:03:39','1789192976_111_reading.webm','uploads/audio/1789192976_111_reading.webm',10,1,'Completed'),(5,111,47,NULL,'2026-09-12 17:10:11','2026-09-12 17:10:11','2026-09-12 17:11:33','1789204211_111_reading.webm','uploads/audio/1789204211_111_reading.webm',10,1,'Completed'),(6,109,43,NULL,'2026-09-12 17:40:36','2026-09-12 17:40:36','2026-09-12 17:41:17','1789206036_109_reading.webm','uploads/audio/1789206036_109_reading.webm',11,1,'Completed'),(7,110,43,8,'2026-09-13 11:50:54','2026-09-13 11:50:54','2026-09-13 11:52:04','1789271454_110_reading.webm','uploads/audio/1789271454_110_reading.webm',10,1,'Completed');
/*!40000 ALTER TABLE `reading_activity` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reading_assignment`
--

DROP TABLE IF EXISTS `reading_assignment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_assignment` (
  `assignment_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `instructions` text DEFAULT NULL,
  `assigned_at` datetime NOT NULL DEFAULT current_timestamp(),
  `due_date` datetime DEFAULT NULL,
  `status` enum('draft','assigned','completed','archived') NOT NULL DEFAULT 'assigned',
  PRIMARY KEY (`assignment_id`),
  KEY `idx_assignment_teacher` (`teacher_id`),
  KEY `idx_assignment_class` (`class_id`),
  KEY `idx_assignment_status` (`status`),
  CONSTRAINT `fk_assignment_class` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_assignment_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reading_assignment`
--

LOCK TABLES `reading_assignment` WRITE;
/*!40000 ALTER TABLE `reading_assignment` DISABLE KEYS */;
INSERT INTO `reading_assignment` VALUES (3,1,1,'Test Assignment - Ang Batang Matulungin','Please read the assigned material carefully and complete the activity.','2026-08-22 19:55:57',NULL,'assigned'),(5,1,1,'My family',NULL,'2026-09-06 14:54:27','2026-09-07 00:00:00','archived'),(6,1,1,'My family','hello','2026-09-06 15:00:58','2026-09-07 00:00:00','archived'),(7,1,1,'My family','Read and answer the 5 question quiz.','2026-09-06 15:50:50','2026-09-10 00:00:00','archived'),(8,1,1,'My family','Read the Material and answer the 5 questions.','2026-09-06 16:10:38','2026-09-10 00:00:00','archived'),(9,1,1,'Ang batang Matulungin','Basahin at unawain, pagkatapos ay sagutan ang mga sumusunod na katanungan.','2026-09-06 22:37:30','2026-09-10 00:00:00','assigned'),(10,1,1,'My Little Brother','Read carefully and answer the following questions.','2026-09-07 01:01:42','2026-09-09 00:00:00','assigned'),(11,6,30,'Assessment Task 1','Read \"My name is Johnny\" and answer the following questions.','2026-09-11 03:00:54','2026-09-13 00:00:00','archived'),(12,6,30,'Assessment Task 2','Read and answer the following.','2026-09-12 17:39:50','2026-09-13 00:00:00','assigned');
/*!40000 ALTER TABLE `reading_assignment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reading_assignment_material`
--

DROP TABLE IF EXISTS `reading_assignment_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_assignment_material` (
  `assignment_material_id` int(11) NOT NULL AUTO_INCREMENT,
  `assignment_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
  `assigned_order` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`assignment_material_id`),
  UNIQUE KEY `uq_assignment_material` (`assignment_id`,`material_id`),
  KEY `idx_ram_assignment` (`assignment_id`),
  KEY `idx_ram_material` (`material_id`),
  CONSTRAINT `fk_ram_assignment` FOREIGN KEY (`assignment_id`) REFERENCES `reading_assignment` (`assignment_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ram_material` FOREIGN KEY (`material_id`) REFERENCES `reading_material` (`material_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reading_assignment_material`
--

LOCK TABLES `reading_assignment_material` WRITE;
/*!40000 ALTER TABLE `reading_assignment_material` DISABLE KEYS */;
INSERT INTO `reading_assignment_material` VALUES (4,8,41,1),(6,10,41,1),(7,11,47,1),(8,12,43,1);
/*!40000 ALTER TABLE `reading_assignment_material` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reading_material`
--

DROP TABLE IF EXISTS `reading_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_material` (
  `material_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `language` enum('English','Filipino') NOT NULL,
  `material_type` enum('Phil-IRI','CRLA','Practice','Custom') NOT NULL,
  `grade_level` enum('Grade 2','Grade 3') NOT NULL,
  `difficulty` enum('Easy','Average','Hard') DEFAULT 'Average',
  `original_filename` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) NOT NULL,
  `ocr_text` longtext DEFAULT NULL,
  `total_words` int(11) DEFAULT 0,
  `upload_date` datetime DEFAULT current_timestamp(),
  `status` enum('Active','Archived') DEFAULT 'Active',
  `image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`material_id`),
  KEY `idx_material_teacher` (`teacher_id`),
  KEY `idx_material_grade` (`grade_level`),
  CONSTRAINT `reading_material_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reading_material`
--

LOCK TABLES `reading_material` WRITE;
/*!40000 ALTER TABLE `reading_material` DISABLE KEYS */;
INSERT INTO `reading_material` VALUES (3,1,'Ang Batang Matulungin','A Filipino story about a helpful child','Filipino','CRLA','Grade 2','Easy','sample_filipino.jpg','sample_filipino.jpg','Ang batang matulungin ay laging handang tumulong sa kanyang mga kaibigan at pamilya. Siya ay mabait at mapagmahal sa kapwa.',25,'2026-07-25 21:31:05','Active',NULL),(38,1,'Recess Time','','English','CRLA','Grade 2','Hard','783654945_1374876501293518_6769868528839857756_n.jpg','1787892956_783654945_1374876501293518_6769868528839857756_n.jpg','Clang! Clang! It was the bell for recess. The children lined up. Some went out to play. Others went to the toilet. There were those who went to the school canteen to buy something to eat.\n\n\"I will buy a banana,\" Edna said.\n\"I will buy a chocolate,\" Norma said.\nAnita looked at her money and said.\n\" I will buy a sandwich.\"',63,'2026-08-28 12:55:57','Active',NULL),(41,1,'My Little Brother','Love our little brother.','English','Practice','Grade 3','Average','782056006_1648077403403568_6514425411348829300_n.jpg','1788185084_782056006_1648077403403568_6514425411348829300_n.jpg','I am Billy. He is my younger brother. His name is Thomas. He is 5 years old. He loves playing football. I help him with his studies. He is very cute and I love him.',35,'2026-08-31 22:04:45','Active',NULL),(43,1,'The Boy and His Jeep','The boy and his jeep.','English','Custom','Grade 3','Average','782276378_1751611246070626_5586821839370576848_n.jpg','1788893959_782276378_1751611246070626_5586821839370576848_n.jpg','This little boy is in a jeep. His jeep is green. The little boy loves his jeep. He rides and rides.',21,'2026-09-09 02:59:20','Active',NULL),(46,1,'I am Emma','Introducing Emma.','English','Custom','Grade 2','Easy','781867291_1829861501773825_439101048492603392_n.jpg','1789059589_781867291_1829861501773825_439101048492603392_n.jpg','Hello, | am Emma. | am 7 years old. | live in Canada. My hobbies are singing and dancing. | like painting, too.',18,'2026-09-11 00:59:50','Active',NULL),(47,1,'My Name is Johnny','Introducing Johnny.','English','Custom','Grade 2','Easy','777591478_1048493778007029_8250406477967388276_n.jpg','1789061890_777591478_1048493778007029_8250406477967388276_n.jpg','My name is Johnny. I am 9 years old. I live in London. I have a younger brother. His name is Jim. Jim is 5 years old.',27,'2026-09-11 01:38:11','Active',NULL);
/*!40000 ALTER TABLE `reading_material` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report`
--

DROP TABLE IF EXISTS `report`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `report` (
  `report_id` int(11) NOT NULL AUTO_INCREMENT,
  `principal_id` int(11) NOT NULL,
  `report_title` varchar(255) DEFAULT NULL,
  `report_type` enum('Student','Teacher','Class','School') DEFAULT NULL,
  `generated_at` datetime DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`report_id`),
  KEY `principal_id` (`principal_id`),
  CONSTRAINT `report_ibfk_1` FOREIGN KEY (`principal_id`) REFERENCES `principal` (`principal_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report`
--

LOCK TABLES `report` WRITE;
/*!40000 ALTER TABLE `report` DISABLE KEYS */;
/*!40000 ALTER TABLE `report` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student`
--

DROP TABLE IF EXISTS `student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student` (
  `student_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `lrn` varchar(20) DEFAULT NULL,
  `teacher_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `class_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `archived_at` datetime DEFAULT NULL,
  `date_registered` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`student_id`),
  UNIQUE KEY `lrn` (`lrn`),
  UNIQUE KEY `uq_student_user` (`user_id`),
  KEY `class_id` (`class_id`),
  KEY `idx_lrn` (`lrn`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_grade` (`category_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `student_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`),
  CONSTRAINT `student_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `student_category` (`category_id`),
  CONSTRAINT `student_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student`
--

LOCK TABLES `student` WRITE;
/*!40000 ALTER TABLE `student` DISABLE KEYS */;
INSERT INTO `student` VALUES (29,17,'110678',1,1,1,'Sasi','Tandang','Vitug','Female','2026-07-06',1,NULL,'2026-08-08 20:28:40'),(30,18,'110675',1,1,NULL,'Rue','Tandang','Vitug','Male','2026-07-06',1,NULL,'2026-08-10 03:10:46'),(97,24,'110990',6,2,30,'Hay','Tandang','Vitug','Female','2026-07-06',1,NULL,'2026-08-31 18:16:44'),(108,45,'101222',6,2,30,'Juan','Santos','Dela Cruz','Male',NULL,1,NULL,'2026-09-11 03:15:37'),(109,46,'202333',6,2,30,'Maria','Reyes','Clara','Female',NULL,1,NULL,'2026-09-11 03:15:37'),(110,47,'303444',6,2,30,'Jose','Mercado','Rizal','Male',NULL,1,NULL,'2026-09-11 03:15:37'),(111,48,'404555',6,2,30,'Andres','Castro','Bonifacio','Male',NULL,1,NULL,'2026-09-11 03:15:37'),(112,49,'505666',6,2,30,'Gabriela','Diego','Silang','Female',NULL,1,NULL,'2026-09-11 03:15:37');
/*!40000 ALTER TABLE `student` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_category`
--

DROP TABLE IF EXISTS `student_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_level` enum('Grade 2','Grade 3') NOT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_category`
--

LOCK TABLES `student_category` WRITE;
/*!40000 ALTER TABLE `student_category` DISABLE KEYS */;
INSERT INTO `student_category` VALUES (1,'Grade 2','2025-2026'),(2,'Grade 3','2025-2026'),(3,'','2026-2027');
/*!40000 ALTER TABLE `student_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `student_parent`
--

DROP TABLE IF EXISTS `student_parent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `student_parent` (
  `student_parent_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `relationship` enum('Mother','Father','Guardian') NOT NULL,
  PRIMARY KEY (`student_parent_id`),
  UNIQUE KEY `unique_student_parent` (`student_id`,`parent_id`),
  KEY `parent_id` (`parent_id`),
  CONSTRAINT `student_parent_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `student_parent_ibfk_2` FOREIGN KEY (`parent_id`) REFERENCES `parent` (`parent_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_parent`
--

LOCK TABLES `student_parent` WRITE;
/*!40000 ALTER TABLE `student_parent` DISABLE KEYS */;
/*!40000 ALTER TABLE `student_parent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher`
--

DROP TABLE IF EXISTS `teacher`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher` (
  `teacher_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `teacher_category_id` int(11) NOT NULL,
  `employee_no` varchar(30) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `department` varchar(255) DEFAULT 'Elementary',
  PRIMARY KEY (`teacher_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `employee_no` (`employee_no`),
  CONSTRAINT `teacher_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher`
--

LOCK TABLES `teacher` WRITE;
/*!40000 ALTER TABLE `teacher` DISABLE KEYS */;
INSERT INTO `teacher` VALUES (1,3,0,'T001','Maria','Santos','Dela Cruz','maria.delacruz@scces.edu.ph','09123456789','Elementary'),(6,16,0,NULL,'Vernice',NULL,'Vitug','vernice.vitug@school.org',NULL,'Elementary');
/*!40000 ALTER TABLE `teacher` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_category`
--

DROP TABLE IF EXISTS `teacher_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_category` (
  `teacher_category_id` int(11) NOT NULL AUTO_INCREMENT,
  `grade_level` enum('Grade 2','Grade 3') NOT NULL,
  `section` varchar(50) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  PRIMARY KEY (`teacher_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_category`
--

LOCK TABLES `teacher_category` WRITE;
/*!40000 ALTER TABLE `teacher_category` DISABLE KEYS */;
INSERT INTO `teacher_category` VALUES (1,'Grade 2','Section A','2025-2026'),(2,'Grade 2','Section B','2025-2026'),(3,'Grade 3','Section A','2025-2026'),(4,'Grade 3','Section B','2025-2026'),(5,'Grade 3','Apitong','2026-2027');
/*!40000 ALTER TABLE `teacher_category` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `teacher_student`
--

DROP TABLE IF EXISTS `teacher_student`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `teacher_student` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_teacher_student` (`teacher_id`,`student_id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `teacher_student_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_student_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_student`
--

LOCK TABLES `teacher_student` WRITE;
/*!40000 ALTER TABLE `teacher_student` DISABLE KEYS */;
/*!40000 ALTER TABLE `teacher_student` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Principal','Teacher','Parent','Student') NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_at` datetime DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL,
  `failed_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_ip` varchar(45) DEFAULT NULL,
  `last_device` varchar(255) DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expiry` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_user_status` (`username`,`status`)
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Admin','Active','2026-07-25 21:31:05',NULL,1,NULL,NULL,NULL,NULL,NULL),(2,'principal1','$2y$10$sMp/1UfYNhmZUI4sA3Hki.J8Sx0GpVdhg5dMgpZ2MBNuNi9NkmJoq','Principal','Active','2026-07-25 21:31:05','2026-09-13 01:48:12',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0',NULL,NULL),(3,'maria.delacruz@scces.edu.ph','$2y$10$5N18armFczvSZSj0uq6rSei04McbTlskz0yy4dZzB9HVyhEBPmAdS','Teacher','Active','2026-07-25 21:31:05','2026-09-11 00:30:50',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0',NULL,NULL),(4,'jose.mercado@scces.edu.ph','$2y$10$vpY8FyY5bbFsKzIGmRkl1e/hZzrm8Yre/b7MjYp1Foi0YZwh320Pm','Teacher','Active','2026-07-25 21:31:05','2026-09-11 00:24:12',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0',NULL,NULL),(5,'parent1','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Parent','Active','2026-07-25 21:31:05',NULL,1,NULL,NULL,NULL,NULL,NULL),(6,'teacher4','password123','Teacher','Active','2026-07-26 01:19:51',NULL,0,NULL,NULL,NULL,NULL,NULL),(8,'teacher1','$2y$10$f6/fr0yTMztp6T42rY1gu.mJGLe/nQtHmxMkGkPD8okeXwN9BOusy','Teacher','Active','2026-08-08 14:41:36',NULL,0,NULL,NULL,NULL,NULL,NULL),(16,'vernice.vitug','$2y$10$JU6pk3g60WSC9pVxZleCTO8qEGfautqeLbNJg2XQ7OsEJS8xyNe9C','Teacher','Active','2026-08-31 17:14:58','2026-09-14 01:35:00',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36 Edg/153.0.0.0',NULL,NULL),(17,'110678','$2y$10$OFKuJrvDA67XVA/9sNx19.abLhScutd/waymTPBx0/5BQcspL7.KW','Student','Active','2026-09-04 23:29:11','2026-09-09 01:56:59',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0',NULL,NULL),(18,'110675','$2y$10$G4Q076EAt5CiPJa9Guz.cOPTQQ6WJPBSfFyps4ggLqomrVlhO5kGa','Student','Active','2026-09-04 23:29:12',NULL,0,NULL,NULL,NULL,NULL,NULL),(19,'116821','$2y$10$xUxu1jvB0OoISy5eHPFMde1JmPpFZJSRAAzX/Z1rMUQu9qA6HGgei','Student','Active','2026-09-04 23:29:12',NULL,0,NULL,NULL,NULL,NULL,NULL),(20,'116876','$2y$10$z6w25SjCzPfQN9gPXVI4vOT.SF0VFLLM2y518fBMxr1n690VWJpRO','Student','Active','2026-09-04 23:29:12','2026-09-08 02:00:18',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0',NULL,NULL),(21,'116873','$2y$10$loEGC9nZomEhF3FGUxHdd.X2LA509KYl6mKplqxOBVcQ7iJIoVOA2','Student','Active','2026-09-04 23:29:12',NULL,0,NULL,NULL,NULL,NULL,NULL),(22,'112354','$2y$10$qc0ZmBFcA6ZjGEr9w0s38u1LtkvmsZ.h1LQKi2bEtsUPSW0KViZpS','Student','Active','2026-09-04 23:29:12',NULL,0,NULL,NULL,NULL,NULL,NULL),(23,'122479','$2y$10$SoLunv6O7YA9toiYTJgzp.7u5Q9pvMhZiiYkfu8uKC1pG6yk/fZ8G','Student','Active','2026-09-04 23:29:12',NULL,0,NULL,NULL,NULL,NULL,NULL),(24,'110990','$2y$10$/1j09qkcsTTXba/kw./8xuTpS6EYOBwAKRonMiM8gM/q4OF095Tje','Student','Active','2026-09-04 23:29:12','2026-09-09 02:44:28',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0',NULL,NULL),(45,'101222','$2y$10$l9vzCvafJSeZuxbqsisk5.8tK2i9sAILCNr7zvQ4XBK.YQySPFZ6C','Student','Active','2026-09-11 03:15:37',NULL,0,NULL,NULL,NULL,NULL,NULL),(46,'202333','$2y$10$S.8cxzkBxQMJgpDe0yZimeFYsRVKF6iCxP2suYlmT55s/Utdjwlc6','Student','Active','2026-09-11 03:15:37','2026-09-13 03:45:46',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0',NULL,NULL),(47,'303444','$2y$10$a5JvqeIP/g6uCYHBbWPv3.QWwlDZ23VJL8g0eiZloX9.4w//eawAu','Student','Active','2026-09-11 03:15:37','2026-09-13 11:50:05',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0',NULL,NULL),(48,'404555','$2y$10$7xZr2dzTfks28FEkQZ9y2ezmB9U2.qluS/M7xeBtVu4BkV4gb7d3y','Student','Active','2026-09-11 03:15:37','2026-09-12 17:09:35',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0',NULL,NULL),(49,'505666','$2y$10$37HxAQPujKGr28C7V4bVpu9.zfPGs/iB2xtjh5Iw8v55kKD2UN/pS','Student','Active','2026-09-11 03:15:37',NULL,0,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'archivevox'
--

--
-- Dumping routines for database 'archivevox'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-14  3:34:46

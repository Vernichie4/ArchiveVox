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
  `reading_level` enum('Non-reader','Frustration','Instructional','Independent') DEFAULT NULL,
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
  `pronunciation_score` decimal(5,2) DEFAULT NULL,
  `fluency_score` decimal(5,2) DEFAULT NULL,
  `expression_score` decimal(5,2) DEFAULT NULL,
  `assessed_at` datetime DEFAULT current_timestamp(),
  `confidence_score` decimal(5,4) DEFAULT NULL,
  `word_timings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`word_timings`)),
  PRIMARY KEY (`assessment_id`),
  KEY `idx_assessment_activity` (`activity_id`),
  CONSTRAINT `assessment_result_ibfk_1` FOREIGN KEY (`activity_id`) REFERENCES `reading_activity` (`activity_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `assessment_result`
--

LOCK TABLES `assessment_result` WRITE;
/*!40000 ALTER TABLE `assessment_result` DISABLE KEYS */;
INSERT INTO `assessment_result` VALUES (7,11,18,0,0.00,0.00,9,1,17,0,0,0,'استرzeit','Non-reader',NULL,0,0,0,NULL,NULL,0,0,0,0,NULL,NULL,'Level 1',NULL,NULL,NULL,'2026-08-08 20:56:00',NULL,NULL),(8,12,18,0,0.00,0.00,9,4,14,0,0,0,'وأentalم -\"وصفو علي مبعاظوع من اماء الله ما ا存نا فتمكنه سما على lût ا pontos','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 00:18:41',NULL,NULL),(9,13,44,8,18.18,20.87,23,36,0,1,8,0,'Here was one of the denny rabbi and in the beginning you are famous landed. You are fat and banshy, as Arabic should be, this coast was spotted round and wide. He has been spread with fish and his ears were lined with pink satin.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 00:20:14',NULL,NULL),(10,14,44,4,9.09,16.00,15,40,0,6,13,0,'Here was one of us that in the round with an in the beginning he was really splendid. He was fat and bungee as a rabbit, so it should be. His coat was spotted down and like he had real fat whiskers and his ears were lying with his fatten.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 01:15:50',NULL,NULL),(11,15,44,2,4.55,6.32,19,42,0,3,13,0,'Here was one such as the Inrabe and in the way he was really standard. He was fat and bungee. As the rabbi should be, he\'s got less fat and down and right. He had been read this church and his fears were lined with faiths.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 01:34:52',NULL,NULL),(12,16,44,24,54.55,75.79,19,18,2,1,0,0,'He was once a resident in Raby and in the beginning he was well-offended. He was back in Banchi, a Arabic city. His coat was spotted down in life. He had long drag misturs. And his ears were lined with boots, cotton.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 01:44:54',NULL,NULL),(13,17,44,19,43.18,71.25,16,24,1,3,0,0,'Here was once a riser in Rabi and in the beginning he was very slummed. He was fat and bouncy as Rabi should be. He scored a slap of down in light. He had yet to progress just as this piece will mind his face pattern.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 01:55:59',NULL,NULL),(14,18,44,10,22.73,35.29,17,34,0,7,20,0,'Here was once a well-written rabbit and in the beginning he was very splendid. He was fat and runny, a parabics should be. His coat was fat and done in life. He had well-traged wrist-s wrist and wrist-s wrist and his ass were nice, twisting, fat and...','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,'Level 1',NULL,NULL,NULL,'2026-08-10 01:59:39',NULL,NULL),(15,19,44,2,4.55,7.50,16,42,0,3,13,0,'Here was one for the Red Dragon Rabbit and in other came the hero for the Shadow, he was fucking Bumsy and for the Rabbit should be with the Cockle Spocker down and like he had raised a beast to his ears for Mound with pink snuckers.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,'Level 1',NULL,NULL,NULL,'2026-08-10 02:01:28',NULL,NULL),(16,34,44,1,2.27,3.33,18,41,2,0,0,0,'The city of St. Petersburg is a country that is not a city, but a country that is not a city. The city of St. Petersburg is a country that is not a city, but a country that is not a city.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 02:29:38',NULL,NULL),(17,40,44,4,9.09,14.12,17,34,6,0,110,0,'This is the first time I\'ve seen a person who is a fast-food restaurant. As fast as I can, he\'s got a heart attack and my three-year-old friend is taking this year\'s for a long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long, long','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 02:47:15',NULL,NULL),(18,41,44,19,43.18,76.00,15,25,0,5,0,0,'Who was going to die getting murdered and in every game you were sure you were standing? He was fat and bouncy, as a rabbit she sees. He\'s got the spotted crown in life. He had the right stress and stress and his ears were like with the spatting.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-10 02:54:05',NULL,NULL),(19,42,44,2,4.55,8.57,14,42,0,0,11,0,'He was 29 th bedroom in Meralb, almost immediately after he exploded, but was not even a bad start up in the house I said according to his parents\' time I started he happier his house which in his earsment were not in cinch','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,'Level 1',NULL,NULL,NULL,'2026-08-10 03:22:04',NULL,NULL),(20,43,19,7,36.84,70.00,6,12,0,1,2,0,'The family is made up of my dad\'s mom and siblings. They are my inspiration and strike in life.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,'Level 1',NULL,NULL,NULL,'2026-08-10 11:16:05',NULL,NULL),(21,44,72,9,12.50,21.60,25,63,0,8,21,0,'For a long time, he lived in the toy shop board or on the next floor. And no one thought very much about him. But in the today\'s show, M being only made up of a painting. Some of North Spanish guys like South America. The metahonic culture, and none of them are one of the everyone else. They were full of modern ideas and pedantic ideas. And they were really both. The mother\'s both who have lived.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,'Level 1',NULL,NULL,NULL,'2026-08-10 11:35:22',NULL,NULL),(22,45,19,8,42.11,53.33,9,11,0,1,3,0,'My family is made up of my dad\'s mom and siblings. They are my inspiration and strength in life.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,'Level 1',NULL,NULL,NULL,'2026-08-10 14:03:10',NULL,NULL),(23,46,19,8,42.11,53.33,9,11,0,1,3,0,'My family is made up of my dad\'s mom and siblings. They are my inspiration and strength in life.','Non-reader',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,'Level 1',NULL,NULL,NULL,'2026-08-10 14:26:25',NULL,NULL),(24,47,19,18,94.74,90.00,12,1,0,0,0,0,'My family is made up of my dad\'s mom and siblings. They are my inspiration and strength in life.','',NULL,NULL,NULL,NULL,NULL,NULL,0,0,0,0,NULL,NULL,'Level 2',NULL,NULL,NULL,'2026-08-10 14:39:54',NULL,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `class`
--

LOCK TABLES `class` WRITE;
/*!40000 ALTER TABLE `class` DISABLE KEYS */;
INSERT INTO `class` VALUES (1,1,'Grade 2','Section A','2025-2026'),(2,1,'Grade 2','Section B','2025-2026'),(3,2,'Grade 3','Section A','2025-2026'),(4,2,'Grade 3','Section B','2025-2026'),(5,1,'Grade 3','Section A','2026-2027'),(6,1,'Grade 3','Section B','2026-2027');
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
INSERT INTO `crla_criteria` VALUES (1,'Grade 3','Part 1',0,10,NULL,NULL,NULL,NULL,'Low Emerging Reader','Level 1'),(2,'Grade 3','Part 2',0,25,NULL,NULL,NULL,NULL,'High Emerging Reader','Level 1'),(3,'Grade 3','Part 2',26,50,NULL,NULL,NULL,NULL,'Developing Reader','Level 2'),(4,'Grade 3','Part 2',51,75,NULL,NULL,NULL,NULL,'Transitioning Reader','Level 3'),(5,'Grade 3','Part 2',76,100,NULL,NULL,NULL,NULL,'Reading At Grade Level','Level 4');
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
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
INSERT INTO `login_attempts` VALUES (1,NULL,'::1','2026-08-06 17:40:59',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(2,NULL,'::1','2026-08-06 17:41:06',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(3,NULL,'::1','2026-08-06 17:41:23',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(4,NULL,'::1','2026-08-06 17:41:27',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(5,NULL,'::1','2026-08-06 17:41:31',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(6,3,'::1','2026-08-06 17:47:13',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(7,3,'::1','2026-08-06 17:47:45',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(8,NULL,'::1','2026-08-06 17:49:33',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(9,NULL,'::1','2026-08-06 18:11:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(10,NULL,'::1','2026-08-06 18:13:00',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(11,NULL,'::1','2026-08-06 19:32:39',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(12,2,'::1','2026-08-06 19:35:19',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(13,NULL,'::1','2026-08-06 19:37:01',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(14,NULL,'::1','2026-08-06 19:38:39',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(15,1,'::1','2026-08-06 19:43:24',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(16,2,'::1','2026-08-06 19:43:24',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(17,3,'::1','2026-08-06 19:43:24',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(18,4,'::1','2026-08-06 19:43:25',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(19,5,'::1','2026-08-06 19:43:25',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(20,NULL,'::1','2026-08-06 19:49:14',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(21,NULL,'::1','2026-08-06 19:49:44',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(22,3,'::1','2026-08-06 19:56:46',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(23,NULL,'::1','2026-08-06 20:05:47',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(24,3,'::1','2026-08-06 20:06:06',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(25,NULL,'::1','2026-08-06 21:39:19',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(26,3,'::1','2026-08-06 21:41:30',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(27,2,'::1','2026-08-06 22:04:36',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(28,4,'::1','2026-08-06 22:05:36',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(29,3,'::1','2026-08-08 10:31:50',0,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(30,3,'::1','2026-08-08 10:31:59',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(31,2,'::1','2026-08-08 10:42:15',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(32,3,'::1','2026-08-08 10:52:15',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(33,4,'::1','2026-08-08 11:06:10',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(34,3,'::1','2026-08-08 11:14:16',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(35,4,'::1','2026-08-08 11:23:58',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(36,4,'::1','2026-08-08 13:17:24',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(37,4,'::1','2026-08-08 16:52:41',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(38,4,'::1','2026-08-08 20:00:02',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(39,2,'::1','2026-08-08 20:00:31',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(40,2,'::1','2026-08-08 20:07:23',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(41,4,'::1','2026-08-08 20:07:58',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(42,4,'::1','2026-08-08 20:08:06',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(43,4,'::1','2026-08-08 20:08:53',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(44,2,'::1','2026-08-08 20:09:08',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(45,2,'::1','2026-08-08 20:20:49',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(46,3,'::1','2026-08-08 20:27:51',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(47,3,'::1','2026-08-10 03:21:09',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(48,3,'::1','2026-08-10 03:28:56',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(49,3,'::1','2026-08-10 11:08:17',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(50,2,'::1','2026-08-10 11:50:14',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(51,4,'::1','2026-08-10 11:50:45',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(52,4,'::1','2026-08-10 12:52:35',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(53,4,'::1','2026-08-10 12:53:58',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(54,2,'::1','2026-08-10 13:11:06',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(55,2,'::1','2026-08-10 13:44:25',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(56,4,'::1','2026-08-10 14:01:12',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(57,4,'::1','2026-08-10 14:58:06',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(58,4,'::1','2026-08-12 13:37:32',1,'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0');
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
) ENGINE=InnoDB AUTO_INCREMENT=50 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_logs`
--

LOCK TABLES `login_logs` WRITE;
/*!40000 ALTER TABLE `login_logs` DISABLE KEYS */;
INSERT INTO `login_logs` VALUES (1,3,'2026-08-06 17:47:13',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(2,3,'2026-08-06 17:47:45',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(7,2,'2026-08-06 19:35:19',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(10,2,'2026-08-06 19:43:24',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(11,3,'2026-08-06 19:43:24',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(14,3,'2026-08-06 19:56:46',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(16,3,'2026-08-06 20:06:06',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(18,3,'2026-08-06 21:41:30',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(19,2,'2026-08-06 22:04:36',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(20,4,'2026-08-06 22:05:36',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(21,3,'2026-08-08 10:31:59',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(22,2,'2026-08-08 10:42:15',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(23,3,'2026-08-08 10:52:15',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(24,4,'2026-08-08 11:06:10',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(25,3,'2026-08-08 11:14:16',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(26,4,'2026-08-08 11:23:58',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(27,4,'2026-08-08 13:17:24',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(28,4,'2026-08-08 16:52:41',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(29,4,'2026-08-08 20:00:02',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(30,2,'2026-08-08 20:00:31',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(31,2,'2026-08-08 20:07:23',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(32,4,'2026-08-08 20:07:58',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(33,4,'2026-08-08 20:08:06',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(34,4,'2026-08-08 20:08:53',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(35,2,'2026-08-08 20:09:08',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(36,2,'2026-08-08 20:20:49',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(37,3,'2026-08-08 20:27:51',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(38,3,'2026-08-10 03:21:09',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(39,3,'2026-08-10 03:28:56',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(40,3,'2026-08-10 11:08:17',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(41,2,'2026-08-10 11:50:14',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(42,4,'2026-08-10 11:50:45',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(43,4,'2026-08-10 12:52:35',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(44,4,'2026-08-10 12:53:58',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(45,2,'2026-08-10 13:11:06',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(46,2,'2026-08-10 13:44:25',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(47,4,'2026-08-10 14:01:12',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(48,4,'2026-08-10 14:58:06',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0'),(49,4,'2026-08-12 13:37:32',NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0');
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
  `performance_level` enum('Non-reader','Frustration','Instructional','Independent') DEFAULT NULL,
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
-- Table structure for table `reading_activity`
--

DROP TABLE IF EXISTS `reading_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_activity` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `material_id` int(11) NOT NULL,
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
  CONSTRAINT `reading_activity_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE,
  CONSTRAINT `reading_activity_ibfk_2` FOREIGN KEY (`material_id`) REFERENCES `reading_material` (`material_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reading_activity`
--

LOCK TABLES `reading_activity` WRITE;
/*!40000 ALTER TABLE `reading_activity` DISABLE KEYS */;
INSERT INTO `reading_activity` VALUES (11,9,5,'2026-08-08 20:54:58','2026-08-08 20:54:58','2026-08-08 20:56:00','1786193698_9_reading.webm','uploads/audio/1786193698_9_reading.webm',9,1,'Completed'),(12,29,5,'2026-08-10 00:17:57','2026-08-10 00:17:57','2026-08-10 00:18:41','1786292277_29_reading.webm','uploads/audio/1786292277_29_reading.webm',9,1,'Completed'),(13,29,34,'2026-08-10 00:20:03','2026-08-10 00:20:03','2026-08-10 00:20:14','1786292403_29_reading.webm','uploads/audio/1786292403_29_reading.webm',23,1,'Completed'),(14,29,34,'2026-08-10 01:15:39','2026-08-10 01:15:39','2026-08-10 01:15:50','1786295739_29_reading.webm','uploads/audio/1786295739_29_reading.webm',15,1,'Completed'),(15,29,34,'2026-08-10 01:34:36','2026-08-10 01:34:36','2026-08-10 01:34:52','1786296876_29_reading.webm','uploads/audio/1786296876_29_reading.webm',19,1,'Completed'),(16,29,34,'2026-08-10 01:44:42','2026-08-10 01:44:42','2026-08-10 01:44:54','1786297482_29_reading.webm','uploads/audio/1786297482_29_reading.webm',19,1,'Completed'),(17,29,34,'2026-08-10 01:55:38','2026-08-10 01:55:38','2026-08-10 01:55:59','1786298138_29_reading.webm','uploads/audio/1786298138_29_reading.webm',16,1,'Completed'),(18,29,34,'2026-08-10 01:59:19','2026-08-10 01:59:19','2026-08-10 01:59:39','1786298359_29_reading.webm','uploads/audio/1786298359_29_reading.webm',17,1,'Completed'),(19,29,34,'2026-08-10 02:01:12','2026-08-10 02:01:12','2026-08-10 02:01:28','1786298471_29_reading.webm','uploads/audio/1786298471_29_reading.webm',16,1,'Completed'),(34,29,34,'2026-08-10 02:29:23','2026-08-10 02:29:23','2026-08-10 02:29:38','1786300163_29_reading.webm','uploads/audio/1786300163_29_reading.webm',18,1,'Completed'),(40,29,34,'2026-08-10 02:46:46','2026-08-10 02:46:46','2026-08-10 02:47:15','1786301206_29_reading.webm','uploads/audio/1786301206_29_reading.webm',17,1,'Completed'),(41,29,34,'2026-08-10 02:53:44','2026-08-10 02:53:44','2026-08-10 02:54:05','1786301624_29_reading.webm','uploads/audio/1786301624_29_reading.webm',15,1,'Completed'),(42,29,34,'2026-08-10 03:21:38','2026-08-10 03:21:38','2026-08-10 03:22:04','1786303298_29_reading.webm','uploads/audio/1786303298_29_reading.webm',14,1,'Completed'),(43,29,35,'2026-08-10 11:15:41','2026-08-10 11:15:41','2026-08-10 11:16:05','1786331741_29_reading.webm','uploads/audio/1786331741_29_reading.webm',6,1,'Completed'),(44,29,36,'2026-08-10 11:35:10','2026-08-10 11:35:10','2026-08-10 11:35:22','1786332910_29_reading.webm','uploads/audio/1786332910_29_reading.webm',25,1,'Completed'),(45,26,35,'2026-08-10 14:03:00','2026-08-10 14:03:00','2026-08-10 14:03:10','1786341780_26_reading.webm','uploads/audio/1786341780_26_reading.webm',9,1,'Completed'),(46,26,35,'2026-08-10 14:26:15','2026-08-10 14:26:15','2026-08-10 14:26:25','1786343175_26_reading.webm','uploads/audio/1786343175_26_reading.webm',9,1,'Completed'),(47,26,35,'2026-08-10 14:39:44','2026-08-10 14:39:44','2026-08-10 14:39:54','1786343984_26_reading.webm','uploads/audio/1786343984_26_reading.webm',12,1,'Completed');
/*!40000 ALTER TABLE `reading_activity` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reading_material`
--

DROP TABLE IF EXISTS `reading_material`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reading_material` (
  `material_id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
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
  PRIMARY KEY (`material_id`),
  KEY `idx_material_teacher` (`teacher_id`),
  KEY `idx_material_grade` (`grade_level`),
  CONSTRAINT `reading_material_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reading_material`
--

LOCK TABLES `reading_material` WRITE;
/*!40000 ALTER TABLE `reading_material` DISABLE KEYS */;
INSERT INTO `reading_material` VALUES (3,1,'Ang Batang Matulungin','A Filipino story about a helpful child','Filipino','CRLA','Grade 2','Easy','sample_filipino.jpg','sample_filipino.jpg','Ang batang matulungin ay laging handang tumulong sa kanyang mga kaibigan at pamilya. Siya ay mabait at mapagmahal sa kapwa.',25,'2026-07-25 21:31:05','Active'),(5,2,'Ang Pamilya Ko','Filipino reading passage about family','Filipino','Practice','Grade 3','Average','sample_family_fil.jpg','sample_family_fil.jpg','Ang pamilya ko ay binubuo ng aking ama, ina, at mga kapatid. Sila ay aking inspirasyon at lakas.',20,'2026-07-25 21:31:05','Active'),(34,1,'Bunny','Story of a bunny.','English','Practice','Grade 2','Average','Screenshot 2026-07-28 003017.png','1786192906_Screenshot 2026-07-28 003017.png','Here was once a velveteen rabbit, and in the beginning he was really splendid. He was fat and bunchy, as a rabbit should be; his coat was spotted brown and white, he had real thread whiskers, and his ears were lined with pink sateen.',44,'2026-08-08 20:41:47','Active'),(35,1,'My Family','English reading passage about family.','English','Practice','Grade 2','Average','Screenshot 2026-08-10 111015.png','1786331472_Screenshot 2026-08-10 111015.png','My family is made up of my dad, mom, and siblings. They are my inspiration and strength in life.',19,'2026-08-10 11:11:17','Active'),(36,1,'Christmas Morning','A story about a christmas morning.','English','Practice','Grade 2','Hard','Screenshot 2026-08-10 111015.png','1786331480_Screenshot 2026-08-10 111015.png','For a long time he lived in the toy cupboard or on the nursery floor, and no one thought very much about him. He was naturally shy, and being only made of velveteen, some of the more expensive toys quite snubbed him. The mechanical toys were very superior, and looked down upon every one else; they were full of modern ideas, and pretended they were real. The model boat, who had lived',72,'2026-08-10 11:11:21','Active');
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
  KEY `class_id` (`class_id`),
  KEY `idx_lrn` (`lrn`),
  KEY `idx_teacher` (`teacher_id`),
  KEY `idx_grade` (`category_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `student_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`teacher_id`),
  CONSTRAINT `student_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `student_category` (`category_id`),
  CONSTRAINT `student_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `class` (`class_id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student`
--

LOCK TABLES `student` WRITE;
/*!40000 ALTER TABLE `student` DISABLE KEYS */;
INSERT INTO `student` VALUES (9,'100312',1,2,NULL,'VERNICE','TANDANG','VITUG','Female','2026-07-06',0,NULL,'2026-07-26 23:05:20'),(23,'Santos',2,1,NULL,'Ruel',NULL,'Reyes','Male',NULL,1,NULL,'2026-08-08 18:52:55'),(24,'Rivera',2,2,NULL,'Hamon',NULL,'Torres','Female',NULL,1,NULL,'2026-08-08 18:52:55'),(26,'Dela Cruz',2,2,NULL,'Heart',NULL,'Santos','Female',NULL,1,NULL,'2026-08-08 18:52:55'),(27,'Lopez',2,2,NULL,'Nathaniel',NULL,'Fernandez','Female',NULL,1,NULL,'2026-08-08 19:34:28'),(28,'Garcia',2,1,NULL,'Raymond',NULL,'Mendoza','Male',NULL,1,NULL,'2026-08-08 19:34:28'),(29,'110678',1,1,NULL,'Sasi','Tandang','Vitug','Female','2026-07-06',1,NULL,'2026-08-08 20:28:40'),(30,'110675',1,1,NULL,'Rue','Tandang','Vitug','Male','2026-07-06',1,NULL,'2026-08-10 03:10:46');
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `student_category`
--

LOCK TABLES `student_category` WRITE;
/*!40000 ALTER TABLE `student_category` DISABLE KEYS */;
INSERT INTO `student_category` VALUES (1,'Grade 2','2025-2026'),(2,'Grade 3','2025-2026');
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
  PRIMARY KEY (`teacher_id`),
  UNIQUE KEY `user_id` (`user_id`),
  UNIQUE KEY `employee_no` (`employee_no`),
  KEY `teacher_category_id` (`teacher_category_id`),
  CONSTRAINT `teacher_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `teacher_ibfk_2` FOREIGN KEY (`teacher_category_id`) REFERENCES `teacher_category` (`teacher_category_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher`
--

LOCK TABLES `teacher` WRITE;
/*!40000 ALTER TABLE `teacher` DISABLE KEYS */;
INSERT INTO `teacher` VALUES (1,3,1,'T001','Maria','Santos','Dela Cruz','maria.delacruz@scces.edu.ph','09123456789'),(2,4,3,'T002','Jose','Rizal','Mercado','jose.mercado@scces.edu.ph','09123456788');
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `teacher_category`
--

LOCK TABLES `teacher_category` WRITE;
/*!40000 ALTER TABLE `teacher_category` DISABLE KEYS */;
INSERT INTO `teacher_category` VALUES (1,'Grade 2','Section A','2025-2026'),(2,'Grade 2','Section B','2025-2026'),(3,'Grade 3','Section A','2025-2026'),(4,'Grade 3','Section B','2025-2026');
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
  `role` enum('Admin','Principal','Teacher','Parent') NOT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user`
--

LOCK TABLES `user` WRITE;
/*!40000 ALTER TABLE `user` DISABLE KEYS */;
INSERT INTO `user` VALUES (1,'admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Admin','Active','2026-07-25 21:31:05',NULL,1,NULL,NULL,NULL,NULL,NULL),(2,'principal1','$2y$10$sMp/1UfYNhmZUI4sA3Hki.J8Sx0GpVdhg5dMgpZ2MBNuNi9NkmJoq','Principal','Active','2026-07-25 21:31:05','2026-08-10 13:44:25',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,NULL),(3,'maria.delacruz@scces.edu.ph','$2y$10$5N18armFczvSZSj0uq6rSei04McbTlskz0yy4dZzB9HVyhEBPmAdS','Teacher','Active','2026-07-25 21:31:05','2026-08-10 11:08:17',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,NULL),(4,'jose.mercado@scces.edu.ph','$2y$10$vpY8FyY5bbFsKzIGmRkl1e/hZzrm8Yre/b7MjYp1Foi0YZwh320Pm','Teacher','Active','2026-07-25 21:31:05','2026-08-12 13:37:32',0,NULL,'::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36 Edg/151.0.0.0',NULL,NULL),(5,'parent1','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Parent','Active','2026-07-25 21:31:05',NULL,1,NULL,NULL,NULL,NULL,NULL),(6,'teacher4','password123','Teacher','Active','2026-07-26 01:19:51',NULL,0,NULL,NULL,NULL,NULL,NULL),(8,'teacher1','$2y$10$f6/fr0yTMztp6T42rY1gu.mJGLe/nQtHmxMkGkPD8okeXwN9BOusy','Teacher','Active','2026-08-08 14:41:36',NULL,0,NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `user` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-12 14:11:57

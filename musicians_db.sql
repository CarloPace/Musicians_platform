-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 18, 2025 at 12:53 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

CREATE DATABASE musicians_db;



SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `musicians_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--
USE musicians_db;

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `status` enum('pending','active','locked') DEFAULT 'active',
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `2fa_secret` varchar(255) DEFAULT NULL,
  `nonce` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- --------------------------------------------------------

--
-- Table structure for table `media`
--

CREATE TABLE `media` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `audio_file_name` varchar(255) DEFAULT NULL,
  `audio_file_size` int(11) DEFAULT NULL,
  `audio_mime_type` varchar(50) DEFAULT NULL,
  `lyrics_file_name` varchar(255) DEFAULT NULL,
  `lyrics_file_size` int(11) DEFAULT NULL,
  `lyrics_mime_type` varchar(50) DEFAULT NULL,
  `is_premium` tinyint(1) DEFAULT 0,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

CREATE TABLE `otps` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `purpose` enum('registration','password_reset') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  `consumed` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `status` enum('pending','active','locked') DEFAULT 'pending',
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_premium` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `media`
--
ALTER TABLE `media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `otps`
--
ALTER TABLE `otps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_status_email` (`status`,`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `media`
--
ALTER TABLE `media`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `otps`
--
ALTER TABLE `otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `media`
--
ALTER TABLE `media`
  ADD CONSTRAINT `media_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;



CREATE USER 'medium'@'localhost' IDENTIFIED BY 'pwX#SfafIy';

CREATE USER 'low'@'localhost' IDENTIFIED BY 'Er8N6te9@Qk';

GRANT SELECT,INSERT,UPDATE,DELETE ON musicians_db.otps TO 'medium'@'localhost';

GRANT SELECT,INSERT,UPDATE,DELETE ON musicians_db.users TO 'medium'@'localhost';

GRANT SELECT,INSERT,UPDATE,DELETE ON musicians_db.media TO 'medium'@'localhost';

GRANT SELECT ON musicians_db.users TO 'low'@'localhost';

GRANT SELECT ON musicians_db.media TO 'low'@'localhost';

ALTER USER 'root'@'localhost' IDENTIFIED BY '-lv@&eM)ed5Rqe.IXNFA';

/*Creating periodic tasks to delete consumed otps and pending users*/

SET GLOBAL event_scheduler = ON;

DELIMITER $$

CREATE EVENT IF NOT EXISTS `delete_consumed_and_expired_otps`
ON SCHEDULE
    EVERY 6 HOUR
    STARTS CURRENT_TIMESTAMP + INTERVAL 6 HOUR
ON COMPLETION PRESERVE
ENABLE
DO BEGIN
   DELETE FROM otps WHERE consumed=1 OR (expires_at<NOW());
    
END $$ -- The 'END' keyword and the delimiter must be present

DELIMITER ;

DELIMITER $$

CREATE EVENT IF NOT EXISTS `delete_pending_users`
ON SCHEDULE
    EVERY 6 HOUR
    STARTS CURRENT_TIMESTAMP + INTERVAL 6 HOUR
ON COMPLETION PRESERVE
ENABLE
DO BEGIN
   DELETE FROM users WHERE `status`= 'pending' AND (NOW() > DATE_ADD(created_at, INTERVAL 6 HOUR));
END $$

DELIMITER ;


/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

/*FAKE RECORDS FOR TESTING*/
/*THE PASSWORD FOR ALL THESE ACCOUNTS IS Plo@b12ax */
 INSERT INTO users (username, email, password_hash, status, created_at)
 VALUES ('foo', 'foo@email.com', 
 '$argon2id$v=19$m=65536,t=4,p=1$V04wTy45Y2x0SzhKUTBlZA$pdQAJEdLHBXCnTHbZ3S96dB6VlVAKVnyB7VqHw9LC4M', 'active', NOW());
 INSERT INTO users (username, email, password_hash, status, created_at)
 VALUES ('bar', 'bar@email.com', 
 '$argon2id$v=19$m=65536,t=4,p=1$V04wTy45Y2x0SzhKUTBlZA$pdQAJEdLHBXCnTHbZ3S96dB6VlVAKVnyB7VqHw9LC4M', 'active', NOW());
 INSERT INTO users (username, email, password_hash, status, created_at)
 VALUES ('dan', 'dan@email.com', 
 '$argon2id$v=19$m=65536,t=4,p=1$V04wTy45Y2x0SzhKUTBlZA$pdQAJEdLHBXCnTHbZ3S96dB6VlVAKVnyB7VqHw9LC4M', 'active', NOW());
 /*password is: admin123*/
INSERT INTO admins (username, email, password_hash, status, created_at, 2fa_secret, nonce)
 VALUES ('admin', 'admin@example.com', '$argon2id$v=19$m=65536,t=4,p=1$cEdmOWhzc3NQcFA1bjk2Yw$O4lcBKFDS2BPTg1YfsbBAznwCgNh9HAkpxQBnDYrEcI', 'active', NOW(), NULL, NULL);
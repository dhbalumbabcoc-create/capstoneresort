-- phpMyAdmin SQL Dump / Resort Management System Complete Database Schema
-- Fully updated with all table columns, relations, auto-increments, and seed data.
-- Generated: 2026-07-29 21:56:32

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `resort_management`
--

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE IF NOT EXISTS `areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `price_regular` decimal(10,2) NOT NULL DEFAULT 160.00,
  `price_discounted` decimal(10,2) NOT NULL DEFAULT 110.00,
  `price_children` decimal(10,2) NOT NULL DEFAULT 110.00,
  `free_below_age` int(11) NOT NULL DEFAULT 6,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`id`, `name`, `price_regular`, `price_discounted`, `price_children`, `free_below_age`, `status`, `created_at`, `updated_at`) VALUES
('1', 'Sinulom', '110.00', '90.00', '60.00', '6', 'active', '2026-02-05 23:27:38', '2026-02-05 23:28:39'),
('2', 'Bolao', '110.00', '90.00', '60.00', '6', 'active', '2026-02-05 23:27:38', '2026-02-05 23:28:27'),
('3', 'BOTH', '160.00', '110.00', '110.00', '6', 'active', '2026-02-05 23:27:47', '2026-02-05 23:27:47'),
('4', 'Sinulom Falls', '160.00', '110.00', '110.00', '6', '', '2026-02-17 16:09:03', '2026-02-22 08:29:34')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `amenities`
--

CREATE TABLE IF NOT EXISTS `amenities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `name`, `description`, `price`, `status`, `created_at`, `updated_at`) VALUES
('1', 'PILLOW', '', '200.00', 'active', '2026-02-06 01:15:51', '2026-02-06 01:15:51'),
('2', 'BED', '', '500.00', 'active', '2026-02-06 01:20:31', '2026-02-06 01:20:31'),
('3', 'SOFA', '', '350.00', 'active', '2026-02-06 01:20:42', '2026-02-06 01:20:42'),
('4', 'TABLE', '', '500.00', 'active', '2026-02-06 01:20:51', '2026-02-06 01:20:51'),
('5', 'CHAIRS', '', '100.00', 'active', '2026-02-06 01:25:40', '2026-02-06 01:25:40')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE IF NOT EXISTS `facilities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('room','cottage','function_hall') NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `max_occupancy` int(11) DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `status` enum('available','unavailable','maintenance','archived') DEFAULT 'available',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `name`, `type`, `description`, `capacity`, `price`, `max_occupancy`, `amenities`, `area_id`, `status`, `created_at`, `updated_at`, `image_path`) VALUES
('1', 'Villa Gracia', 'room', '', '5', '1800.00', '5', 'BED, CHAIRS, PILLOW, TABLE', NULL, 'available', '2026-02-06 00:03:39', '2026-07-25 22:15:26', 'facilities/facility_1784988926_9694.jpg'),
('2', 'Cottage 1', 'room', '', '0', '400.00', '0', 'CHAIRS, TABLE', NULL, 'archived', '2026-02-06 01:26:03', '2026-02-17 16:13:58', NULL),
('3', 'Villa Carolina', 'room', '', '4', '2500.00', '0', 'BED, CHAIRS, PILLOW, SOFA', NULL, 'available', '2026-02-06 17:43:55', '2026-02-06 17:43:55', NULL),
('4', 'Cottage 2', 'cottage', '', '0', '500.00', '0', 'CHAIRS, TABLE', NULL, 'archived', '2026-02-06 17:44:29', '2026-07-24 01:08:28', NULL),
('5', 'Sinulom Cottage 1', 'cottage', '', '8', '500.00', '10', 'CHAIRS, TABLE', NULL, 'available', '2026-02-06 17:44:41', '2026-07-24 01:09:38', 'facilities/facility_1784826578_2005.jpg'),
('6', 'Function Hall 1', 'function_hall', '', '0', '30000.00', '0', 'CHAIRS, TABLE', NULL, 'available', '2026-02-06 17:44:56', '2026-02-06 17:44:56', NULL),
('7', 'Function Hall 2', 'function_hall', '', '0', '50000.00', '0', 'CHAIRS, TABLE', NULL, 'available', '2026-02-06 17:45:11', '2026-07-25 22:21:39', NULL),
('8', 'Function Hall 3', 'function_hall', '', '0', '50000.00', '0', 'CHAIRS, TABLE', NULL, 'archived', '2026-02-06 17:45:27', '2026-07-24 01:11:19', NULL),
('12', 'Villa Candida', 'room', '', '4', '1200.00', '5', 'BED, CHAIRS, PILLOW, TABLE', NULL, 'available', '2026-07-24 01:11:13', '2026-07-24 01:11:13', 'facilities/facility_1784826673_8811.jpg'),
('13', 'Umbrella', 'cottage', '', '4', '500.00', '5', 'CHAIRS, TABLE', NULL, 'available', '2026-07-24 01:11:54', '2026-07-24 01:11:54', 'facilities/facility_1784826714_3244.jpg'),
('14', 'Bamboo', 'cottage', '', '8', '800.00', '10', 'CHAIRS, TABLE', NULL, 'available', '2026-07-24 01:12:30', '2026-07-24 01:12:30', 'facilities/facility_1784826750_9643.jpg'),
('15', 'Umbrella Kubo', 'cottage', '', '5', '600.00', '6', 'CHAIRS, TABLE', NULL, 'available', '2026-07-24 01:13:22', '2026-07-24 01:13:22', 'facilities/facility_1784826802_7802.jpg'),
('16', 'Cottage 2', 'cottage', '', '8', '800.00', '10', 'CHAIRS, TABLE', NULL, 'available', '2026-07-24 01:13:54', '2026-07-24 01:13:54', 'facilities/facility_1784826834_6434.jpg')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('owner','admin','frontdesk','supervisor') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','pending','inactive') DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `email_verified` tinyint(1) NOT NULL DEFAULT 1,
  `setup_otp` varchar(6) DEFAULT NULL,
  `setup_otp_expires` datetime DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `first_name`, `last_name`, `phone`, `address`, `status`, `created_at`, `updated_at`, `must_change_password`, `email_verified`, `setup_otp`, `setup_otp_expires`, `profile_photo`) VALUES
('1', 'owner', '$2y$10$wLrtFxKYj8pTP2jb7c25Wu1DHtIaSD7F3sw7GsnHmBHMGR9xGlc.O', 'owner@resort.com', 'owner', 'Ziah', 'Bernice', '1234567890', '', 'active', '2026-02-05 23:27:38', '2026-07-24 01:05:29', '0', '1', NULL, NULL, 'owner_1_1784824568.jpg'),
('2', 'lin', '$2y$10$pzT7/cRcS95qT/ZAfMEJLefRpfbbvPW2r5R64tpNyraL3Xf/Nk156', 'lyap.bucod.coc@phinmaed.com', 'admin', 'Lyn', 'Bucod', '09122351650', NULL, 'active', '2026-02-05 23:54:06', '2026-07-25 21:47:40', '0', '1', NULL, NULL, NULL),
('3', 'del', '$2y$10$P5k1VJ6BvW/AWo8ZZszgmubGPcVO3WD7lZ52NUEIvlqUu4Mod/49q', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'john Andre', 'Delfin', '09154684', NULL, 'active', '2026-02-05 23:54:35', '2026-07-24 01:07:09', '0', '1', NULL, NULL, NULL),
('4', 'jus', '$2y$10$aFzcSXxyorAcylFKWphFperltP1HxgafsonCk/uB3r5k8ZDnKAzHm', 'jubu.toledo.coc@phinmaed.com', 'supervisor', 'Justin', 'Toledo', '0914686547', NULL, 'active', '2026-02-05 23:55:04', '2026-07-25 21:47:40', '0', '1', NULL, NULL, NULL),
('11', 'kyyyyziel', '$2y$10$N3tKLVpO7BGZbaP.1521iOklDZA.CTU5TeeJP471x1asv7IICjDHy', 'kyziellumbab@gmail.com', 'supervisor', 'Kyziel', 'Lumbab', '09471474410', NULL, 'active', '2026-07-28 15:23:42', '2026-07-28 15:25:20', '0', '1', NULL, NULL, NULL)
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `guest_accounts`
--

CREATE TABLE IF NOT EXISTS `guest_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_pic` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guest_accounts`
--

INSERT INTO `guest_accounts` (`id`, `email`, `password_hash`, `full_name`, `phone`, `created_at`, `updated_at`, `profile_pic`) VALUES
('1', 'dhba.lumbab.coc@phinmaed.com', '$2y$10$.XrbZI9ZTUlSEM8YHb3h0uk/UBXLAQIHAnJdVy1irFDxZFeiyct8y', 'Dhimcer Lumbab', '09664191131', '2026-07-24 00:38:22', '2026-07-28 15:33:46', 'guest_dhba_lumbab_coc_phinmaed_com_1785224026.jpg'),
('3', 'johndoe@example.com', '$2y$10$c3hPiPcf8N63Qe2gZSTKJenNC9tTNSF4aiSabIgeR/LbmuxqLsBqi', 'John Doe', '09171234567', '2026-07-24 00:46:56', '2026-07-24 00:47:59', NULL),
('5', 'testflow@example.com', '$2y$10$vEyQBgX5tN8Qzy28p66r3uOI2sRonCamKZ2LNuH3N32ywNdbshdR2', 'Test Guest', '09171112222', '2026-07-24 00:49:08', '2026-07-24 00:49:08', NULL),
('6', 'lingassassin12@gmail.com', '$2y$10$ysBqJ63QEv4OFzblvIBdMezkydHvBdd.bvZy0FolwXV4jrrRh.aiC', 'James Cruz', '09471474410', '2026-07-24 09:10:40', '2026-07-24 09:10:40', NULL),
('7', 'grachaelmaeo@gmail.com', '$2y$10$WHGXB.nry2kuVdOPG6SPAeKW74XGqny1TENMHRsyj6ipLDgmws8W6', 'Grachael Mae Cantila', '09664191131', '2026-07-29 10:43:02', '2026-07-29 10:43:02', NULL)
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `guest_otps`
--

CREATE TABLE IF NOT EXISTS `guest_otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `guest_name` varchar(100) NOT NULL,
  `contact_number` varchar(30) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `guest_otps`
--

INSERT INTO `guest_otps` (`id`, `email`, `guest_name`, `contact_number`, `otp`, `expires_at`, `created_at`) VALUES
('19', 'grachaelmaeo@gmail.com', 'Grachael Mae Cantila', '09000000000', '359117', '2026-07-29 11:04:07', '2026-07-29 10:54:07'),
('20', 'grachaelmaeo@gmail.com', 'Guest', '09000000000', '681635', '2026-07-29 11:05:02', '2026-07-29 10:55:02')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `guest_password_resets`
--

CREATE TABLE IF NOT EXISTS `guest_password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `guest_id` int(11) NOT NULL,
  `otp` varchar(6) NOT NULL DEFAULT '',
  `token` varchar(64) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_guest_id` (`guest_id`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `otp` varchar(6) NOT NULL DEFAULT '',
  `token` varchar(64) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `resort_name` varchar(255) NOT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `business_hours` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `vat_rate` decimal(5,2) NOT NULL DEFAULT 12.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `resort_name`, `tagline`, `contact_info`, `business_hours`, `logo`, `updated_at`, `vat_rate`) VALUES
('1', 'Sinulom and Bolao', 'Cold Spring', '(example) 0917-123-4567', '8:00 AM - 5:00 PM', 'logo.jpg', '2026-07-24 01:05:51', '0.00')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `audit_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action_performed` text NOT NULL,
  `action_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Active','Archived') DEFAULT 'Active',
  PRIMARY KEY (`audit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs_v2`
--

CREATE TABLE IF NOT EXISTS `audit_logs_v2` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `event_type` varchar(50) NOT NULL,
  `page` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs_v2`
--

INSERT INTO `audit_logs_v2` (`id`, `user_id`, `username`, `role`, `event_type`, `page`, `ip_address`, `user_agent`, `details`, `created_at`) VALUES
('1', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-24 00:35:44'),
('2', '1', 'owner@resort.com', 'owner', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-24 00:36:16'),
('3', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-24 00:36:47'),
('4', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-24 00:37:05'),
('5', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-24 00:37:19'),
('6', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-24 00:37:27'),
('7', NULL, NULL, NULL, 'unauthorized_access', '/capstone/capstone/unauthorized.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Attempted to access restricted page. Referer: http://localhost/capstone/capstone/frontdesk/settings.php', '2026-07-24 00:40:57'),
('8', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-24 00:41:06'),
('9', NULL, NULL, NULL, 'unauthorized_access', '/capstone/capstone/unauthorized.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Attempted to access restricted page. Referer: http://localhost/capstone/capstone/frontdesk/online_bookings.php', '2026-07-24 01:02:03'),
('10', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-24 01:02:37'),
('11', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-24 01:04:08'),
('12', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-24 01:05:28'),
('13', '1', 'owner@resort.com', 'owner', 'unauthorized_access', '/capstone/capstone/unauthorized.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Attempted to access restricted page. Referer: http://localhost/capstone/capstone/frontdesk/dashboard.php', '2026-07-24 01:06:04'),
('14', '1', 'owner@resort.com', 'owner', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-24 01:06:08'),
('15', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-24 01:06:24'),
('16', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-24 01:08:01'),
('17', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-24 01:08:13'),
('18', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-24 09:07:48'),
('19', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-24 09:52:15'),
('20', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-25 21:52:54'),
('21', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-25 22:14:19'),
('22', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-25 22:14:35'),
('23', '1', 'owner@resort.com', 'owner', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-25 22:20:12'),
('24', '2', 'lyap.bucod.coc@phinmaed.com', 'admin', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: admin', '2026-07-25 22:20:22'),
('25', '2', 'lyap.bucod.coc@phinmaed.com', 'admin', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-25 22:20:43'),
('26', '4', 'jubu.toledo.coc@phinmaed.com', 'supervisor', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: supervisor', '2026-07-25 22:20:54'),
('27', '4', 'jubu.toledo.coc@phinmaed.com', 'supervisor', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-25 22:29:53'),
('28', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-27 23:22:39'),
('29', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-27 23:24:59'),
('30', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-27 23:25:08'),
('31', '1', 'owner@resort.com', 'owner', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-27 23:29:33'),
('32', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-28 08:13:14'),
('33', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-28 08:13:15'),
('34', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-28 14:02:08'),
('35', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 14:05:11'),
('36', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-28 14:05:33'),
('37', '1', 'owner@resort.com', 'owner', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 14:08:31'),
('38', '2', 'lyap.bucod.coc@phinmaed.com', 'admin', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: admin', '2026-07-28 14:08:44'),
('39', '2', 'lyap.bucod.coc@phinmaed.com', 'admin', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 14:09:36'),
('40', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-28 14:09:48'),
('41', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 14:17:05'),
('42', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-28 14:17:16'),
('43', '1', 'owner@resort.com', 'owner', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 14:18:53'),
('44', '4', 'jubu.toledo.coc@phinmaed.com', 'supervisor', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: supervisor', '2026-07-28 14:19:05'),
('45', '4', 'jubu.toledo.coc@phinmaed.com', 'supervisor', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 14:19:30'),
('46', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-28 14:19:42'),
('47', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 14:51:01'),
('48', '2', 'lyap.bucod.coc@phinmaed.com', 'admin', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: admin', '2026-07-28 14:51:21'),
('49', '2', 'lyap.bucod.coc@phinmaed.com', 'admin', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 14:51:34'),
('50', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-28 15:02:43'),
('51', '1', 'owner@resort.com', 'owner', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 15:04:18'),
('52', '10', 'kyziellumbab@gmail.com', 'supervisor', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: supervisor', '2026-07-28 15:04:47'),
('53', '10', 'kyziellumbab@gmail.com', 'supervisor', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 15:05:10'),
('54', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-28 15:05:31'),
('55', '1', 'owner@resort.com', 'owner', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: owner', '2026-07-28 15:16:54'),
('56', '1', 'owner@resort.com', 'owner', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 15:23:48'),
('57', '11', 'kyziellumbab@gmail.com', 'supervisor', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: supervisor', '2026-07-28 15:24:09'),
('58', '11', 'kyziellumbab@gmail.com', 'supervisor', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: supervisor', '2026-07-28 15:25:50'),
('59', '11', 'kyziellumbab@gmail.com', 'supervisor', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-28 15:26:11'),
('60', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-29 10:28:55'),
('61', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-29 19:43:19'),
('62', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-29 19:54:50'),
('63', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-29 20:51:19'),
('64', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'logout', '/capstone/capstone/logout.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', NULL, '2026-07-29 21:26:16'),
('65', '3', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'login_success', '/capstone/capstone/login.php', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', 'Role: frontdesk', '2026-07-29 21:32:49')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE IF NOT EXISTS `bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `facility_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `guest_name` varchar(100) NOT NULL,
  `guest_email` varchar(100) DEFAULT NULL,
  `guest_phone` varchar(20) DEFAULT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `num_guests` int(11) DEFAULT NULL,
  `num_adults` int(11) DEFAULT 0,
  `num_discounted` int(11) DEFAULT 0,
  `num_children` int(11) DEFAULT 0,
  `num_below5` int(11) NOT NULL DEFAULT 0,
  `booking_type` enum('walkin','walk-in','online') NOT NULL,
  `mode` enum('overnight','daytour') DEFAULT 'overnight',
  `status` enum('unpaid','pending','confirmed','declined','cancelled','completed') DEFAULT 'pending',
  `total_price` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `facility_id`, `area_id`, `guest_name`, `guest_email`, `guest_phone`, `check_in_date`, `check_out_date`, `num_guests`, `num_adults`, `num_discounted`, `num_children`, `num_below5`, `booking_type`, `mode`, `status`, `total_price`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
('1', '3', '3', 'Lyn-Gei Mae A. Bucod', 'lyap.bucod.coc@phinmaed.com', '09356006157', '2026-02-26', '2026-02-27', '5', '5', '0', '0', '0', 'online', 'overnight', 'declined', '3500.00', NULL, '3', '2026-02-21 21:05:51', '2026-07-24 00:49:11'),
('2', '4', '2', 'Ziah Bernice', 'joshua09242002@gmail.com', '09122351650', '2026-02-28', '2026-02-28', '3', '3', '0', '0', '0', 'online', 'daytour', 'declined', '1330.00', NULL, '3', '2026-02-22 06:32:48', '2026-07-24 00:49:16'),
('11', '1', '1', 'John Doe', 'johndoe@example.com', '09171234567', '2026-07-24', '2026-07-25', '2', '2', '0', '0', '0', 'online', 'overnight', 'declined', '2262.40', 'Time Slot: overnight | Transport: none | Below5: 0 | PWD: 0 | VAT: 242.4', '3', '2026-07-24 00:47:59', '2026-07-24 00:49:21'),
('12', '1', '1', 'Test Guest', 'testflow@example.com', '09171112222', '2026-07-27', '2026-07-28', '3', '2', '0', '1', '0', 'online', 'overnight', 'completed', '2329.60', 'Time Slot: overnight | Transport: none | Below5: 0 | PWD: 0 | VAT: 249.6', '3', '2026-07-24 00:49:08', '2026-07-27 23:23:41'),
('13', '5', '3', 'Dhimcer Lumbab', 'dhba.lumbab.coc@phinmaed.com', '09664191131', '2026-08-05', '2026-08-05', '5', '5', '0', '0', '0', 'online', 'daytour', 'completed', '1456.00', 'Time Slot: full_day | Transport: none | Below5: 0 | PWD: 0 | VAT: 156', '3', '2026-07-24 00:49:48', '2026-07-24 01:06:33'),
('14', '1', '3', 'James Cruz', 'lingassassin12@gmail.com', '09471474410', '2026-07-25', '2026-07-26', '6', '6', '0', '0', '0', 'online', 'overnight', 'completed', '2760.00', 'Time Slot: overnight | Transport: none | Below5: 0 | PWD: 0 | VAT: 0', '3', '2026-07-24 09:10:40', '2026-07-27 23:23:01'),
('15', '14', '3', 'James Cruz', 'lingassassin12@gmail.com', '09471474410', '2026-07-25', '2026-07-26', '0', '0', '0', '0', '0', 'online', 'overnight', 'completed', '800.00', 'Time Slot: overnight | Transport: none | Below5: 0 | PWD: 0 | VAT: 0', '3', '2026-07-24 09:10:40', '2026-07-27 23:23:01'),
('16', '5', '3', 'Dhimcer Lumbab', 'dhba.lumbab.coc@phinmaed.com', '09664191131', '2026-08-29', '2026-08-29', '5', '5', '0', '0', '0', 'online', 'daytour', 'declined', '1456.00', 'Time Slot: full_day | Transport: none | Below5: 0 | PWD: 0 | VAT: 156', '3', '2026-07-27 23:22:16', '2026-07-27 23:22:53'),
('17', '3', '2', 'Grachael Mae Cantila', 'grachaelmaeo@gmail.com', '09664191131', '2026-07-29', '2026-07-30', '4', '2', '2', '0', '0', 'online', 'overnight', 'confirmed', '2896.00', 'Time Slot: overnight | Transport: none | Below5: 0 | PWD: 2 | VAT: 0', '3', '2026-07-29 10:43:02', '2026-07-29 10:49:37')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `booking_addons`
--

CREATE TABLE IF NOT EXISTS `booking_addons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `amenity_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`),
  KEY `facility_id` (`facility_id`),
  KEY `amenity_id` (`amenity_id`),
  CONSTRAINT `booking_addons_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  CONSTRAINT `booking_addons_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`),
  CONSTRAINT `booking_addons_ibfk_3` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_addons`
--

INSERT INTO `booking_addons` (`id`, `booking_id`, `facility_id`, `amenity_id`, `quantity`, `created_at`) VALUES
('1', '1', NULL, '4', '1', '2026-02-22 05:56:25'),
('2', '1', NULL, '1', '1', '2026-02-22 06:15:15'),
('3', '2', NULL, '1', '1', '2026-02-22 06:33:45'),
('4', '2', NULL, '1', '1', '2026-02-22 06:34:06'),
('5', '2', NULL, '5', '1', '2026-02-22 06:34:51')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `booking_area`
--

CREATE TABLE IF NOT EXISTS `booking_area` (
  `booking_area_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_facility`
--

CREATE TABLE IF NOT EXISTS `booking_facility` (
  `booking_facility_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT 1,
  `price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_invoice`
--

CREATE TABLE IF NOT EXISTS `booking_invoice` (
  `booking_invoice_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `total_amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `booking_invoice_item`
--

CREATE TABLE IF NOT EXISTS `booking_invoice_item` (
  `invoice_item_id` int(11) NOT NULL,
  `booking_invoice_id` int(11) DEFAULT NULL,
  `item_type` varchar(50) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `unit_price` decimal(10,2) DEFAULT NULL,
  `item_total` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` int(11) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `method` enum('online','walkin') NOT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `proof_of_payment` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `booking_id` (`booking_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `amount_paid`, `method`, `reference_number`, `proof_of_payment`, `status`, `paid_at`) VALUES
('4', '12', '2329.60', 'online', 'GCASH999888777', NULL, 'completed', '2026-07-24 00:51:53'),
('5', '13', '728.00', 'online', '3040949916742', NULL, 'completed', '2026-07-24 00:52:29'),
('6', '13', '700.00', 'walkin', 'WALKIN-CASH', NULL, 'completed', '2026-07-24 01:03:24'),
('7', '13', '28.00', 'walkin', 'WALKIN-CASH', NULL, 'completed', '2026-07-24 01:03:41'),
('8', '14', '1380.00', 'online', '3040952002805', NULL, 'completed', '2026-07-24 09:10:57'),
('9', '15', '400.00', 'online', '3040952002805', NULL, 'completed', '2026-07-24 09:10:57'),
('10', '14', '1380.00', 'walkin', 'WALKIN-CASH', NULL, 'completed', '2026-07-24 09:12:35'),
('11', '15', '400.00', 'walkin', 'WALKIN-CASH', NULL, 'completed', '2026-07-24 09:13:16'),
('12', '17', '1448.00', 'online', '154656464', NULL, 'completed', '2026-07-29 10:43:26'),
('13', '17', '1200.00', 'online', '3040949916743', NULL, 'completed', '2026-07-29 10:51:06'),
('14', '17', '248.00', 'walkin', 'WALKIN-CASH', NULL, 'completed', '2026-07-29 10:53:09')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE IF NOT EXISTS `maintenance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `facility_id` int(11) NOT NULL,
  `maintenance_type` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed') DEFAULT 'pending',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `scheduled_date` date DEFAULT NULL,
  `completed_date` date DEFAULT NULL,
  `supervisor_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `facility_id` (`facility_id`),
  KEY `supervisor_id` (`supervisor_id`),
  CONSTRAINT `maintenance_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`),
  CONSTRAINT `maintenance_ibfk_2` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance`
--

INSERT INTO `maintenance` (`id`, `facility_id`, `maintenance_type`, `description`, `status`, `priority`, `scheduled_date`, `completed_date`, `supervisor_id`, `notes`, `created_at`, `updated_at`) VALUES
('1', '4', 'REPAIR', '', 'completed', 'high', '2026-02-26', '2026-02-17', '4', NULL, '2026-02-17 08:29:48', '2026-02-18 00:01:50'),
('2', '2', 'REPAIR', '', 'completed', 'medium', '2026-02-19', '2026-02-17', '4', NULL, '2026-02-18 00:01:30', '2026-02-18 00:01:58'),
('3', '7', 'REPAIR', '', 'completed', 'medium', '2026-02-19', '2026-07-25', '4', NULL, '2026-02-18 00:01:37', '2026-07-25 22:21:57'),
('4', '2', 'REPAIR', '', 'in_progress', 'medium', '2026-02-08', NULL, '4', NULL, '2026-02-18 00:01:44', '2026-02-18 00:01:54')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE IF NOT EXISTS `feedback` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `guest_name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `guest_name`, `user_id`, `email`, `rating`, `comment`, `created_at`) VALUES
('1', 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', '5', 'SO GREAT!', '2026-02-17 07:29:37'),
('2', 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', '5', 'SO GREAT!', '2026-02-17 07:30:39'),
('3', 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', '5', 'SO GREAT!', '2026-02-17 07:30:43'),
('4', 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', '5', 'SO GREAT!', '2026-02-17 07:30:48'),
('5', 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', '5', 'SO GREAT!', '2026-02-17 07:30:53'),
('6', 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', '5', 'SO GREAT!', '2026-02-17 07:31:39'),
('7', 'Ziah', NULL, '', '2', 'okay lang', '2026-02-17 08:16:52'),
('8', 'Sheilla Mae', NULL, '', '4', 'cute ko?', '2026-07-24 14:40:54')
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

-- --------------------------------------------------------

--
-- Table structure for table `role`
--

CREATE TABLE IF NOT EXISTS `role` (
  `role_id` int(11) NOT NULL,
  `role_name` enum('owner','front desk','staff','supervisor') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

-- End of schema dump

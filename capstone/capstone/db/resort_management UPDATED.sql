-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 24, 2026 at 04:42 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
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
-- Table structure for table `amenities`
--

CREATE TABLE `amenities` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `amenities`
--

INSERT INTO `amenities` (`id`, `name`, `description`, `price`, `status`, `created_at`, `updated_at`) VALUES
(1, 'PILLOW', '', 200.00, 'active', '2026-02-06 01:15:51', '2026-02-05 17:15:51'),
(2, 'BED', '', 500.00, 'active', '2026-02-06 01:20:31', '2026-02-05 17:20:31'),
(3, 'SOFA', '', 350.00, 'active', '2026-02-06 01:20:42', '2026-02-05 17:20:42'),
(4, 'TABLE', '', 500.00, 'active', '2026-02-06 01:20:51', '2026-02-05 17:20:51'),
(5, 'CHAIRS', '', 100.00, 'active', '2026-02-06 01:25:40', '2026-02-05 17:25:40');

-- --------------------------------------------------------

--
-- Table structure for table `areas`
--

CREATE TABLE `areas` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price_regular` decimal(10,2) NOT NULL DEFAULT 160.00,
  `price_discounted` decimal(10,2) NOT NULL DEFAULT 110.00,
  `price_children` decimal(10,2) NOT NULL DEFAULT 110.00,
  `free_below_age` int(11) NOT NULL DEFAULT 6,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `areas`
--

INSERT INTO `areas` (`id`, `name`, `price_regular`, `price_discounted`, `price_children`, `free_below_age`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Sinulom', 110.00, 90.00, 60.00, 6, 'active', '2026-02-05 23:27:38', '2026-02-05 15:28:39'),
(2, 'Bolao', 110.00, 90.00, 60.00, 6, 'active', '2026-02-05 23:27:38', '2026-02-05 15:28:27'),
(3, 'BOTH', 160.00, 110.00, 110.00, 6, 'active', '2026-02-05 23:27:47', '2026-02-05 15:27:47'),
(4, 'Sinulom Falls', 160.00, 110.00, 110.00, 6, '', '2026-02-17 16:09:03', '2026-02-22 00:29:34');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
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
  `booking_type` enum('walkin','walk-in','online') NOT NULL,
  `mode` enum('overnight','daytour') DEFAULT 'overnight',
  `status` enum('pending','confirmed','declined','cancelled','completed') DEFAULT 'pending',
  `total_price` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `facility_id`, `area_id`, `guest_name`, `guest_email`, `guest_phone`, `check_in_date`, `check_out_date`, `num_guests`, `num_adults`, `num_discounted`, `num_children`, `booking_type`, `mode`, `status`, `total_price`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 3, 3, 'Lyn-Gei Mae A. Bucod', 'lyap.bucod.coc@phinmaed.com', '09356006157', '2026-02-26', '2026-02-27', 5, 5, 0, 0, 'online', 'overnight', 'pending', 3500.00, NULL, NULL, '2026-02-21 21:05:51', '2026-02-21 22:15:15'),
(2, 4, 2, 'Ziah Bernice', 'joshua09242002@gmail.com', '09122351650', '2026-02-28', '2026-02-28', 3, 3, 0, 0, 'online', 'daytour', 'pending', 1330.00, NULL, NULL, '2026-02-22 06:32:48', '2026-02-21 22:34:51');

-- --------------------------------------------------------

--
-- Table structure for table `booking_addons`
--

CREATE TABLE `booking_addons` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `amenity_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_addons`
--

INSERT INTO `booking_addons` (`id`, `booking_id`, `facility_id`, `amenity_id`, `created_at`) VALUES
(1, 1, NULL, 4, '2026-02-22 05:56:25'),
(2, 1, NULL, 1, '2026-02-22 06:15:15'),
(3, 2, NULL, 1, '2026-02-22 06:33:45'),
(4, 2, NULL, 1, '2026-02-22 06:34:06'),
(5, 2, NULL, 5, '2026-02-22 06:34:51');

-- --------------------------------------------------------

--
-- Table structure for table `facilities`
--

CREATE TABLE `facilities` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facilities`
--

INSERT INTO `facilities` (`id`, `name`, `type`, `description`, `capacity`, `price`, `max_occupancy`, `amenities`, `area_id`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Villa Gracia', 'room', '', 5, 1800.00, 0, '', NULL, 'available', '2026-02-06 00:03:39', '2026-02-06 09:44:12'),
(2, 'Cottage 1', 'room', '', 0, 400.00, 0, 'CHAIRS, TABLE', NULL, 'archived', '2026-02-06 01:26:03', '2026-02-17 08:13:58'),
(3, 'Villa Carolina', 'room', '', 4, 2500.00, 0, 'BED, CHAIRS, PILLOW, SOFA', NULL, 'available', '2026-02-06 17:43:55', '2026-02-06 09:43:55'),
(4, 'Cottage 2', 'cottage', '', 0, 500.00, 0, 'CHAIRS, TABLE', NULL, 'available', '2026-02-06 17:44:29', '2026-02-06 09:44:29'),
(5, 'Cottage 3', 'cottage', '', 0, 500.00, 0, 'CHAIRS, TABLE', NULL, 'available', '2026-02-06 17:44:41', '2026-02-06 09:44:41'),
(6, 'Function Hall 1', 'function_hall', '', 0, 30000.00, 0, 'CHAIRS, TABLE', NULL, 'available', '2026-02-06 17:44:56', '2026-02-06 09:44:56'),
(7, 'Function Hall 2', 'function_hall', '', 0, 50000.00, 0, 'CHAIRS, TABLE', NULL, 'available', '2026-02-06 17:45:11', '2026-02-06 09:45:11'),
(8, 'Function Hall 3', 'function_hall', '', 0, 50000.00, 0, 'CHAIRS, TABLE', NULL, 'available', '2026-02-06 17:45:27', '2026-02-06 09:45:27');

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `guest_name` varchar(100) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `guest_name`, `user_id`, `email`, `rating`, `comment`, `created_at`) VALUES
(1, 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', 5, 'SO GREAT!', '2026-02-17 07:29:37'),
(2, 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', 5, 'SO GREAT!', '2026-02-17 07:30:39'),
(3, 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', 5, 'SO GREAT!', '2026-02-17 07:30:43'),
(4, 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', 5, 'SO GREAT!', '2026-02-17 07:30:48'),
(5, 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', 5, 'SO GREAT!', '2026-02-17 07:30:53'),
(6, 'LIN BUCOD', NULL, 'jubu.toledo.coc@phinmaed.com', 5, 'SO GREAT!', '2026-02-17 07:31:39'),
(7, 'Ziah', NULL, '', 2, 'okay lang', '2026-02-17 08:16:52');

-- --------------------------------------------------------

--
-- Table structure for table `guest_otps`
--

CREATE TABLE `guest_otps` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `guest_name` varchar(100) NOT NULL,
  `contact_number` varchar(30) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maintenance`
--

CREATE TABLE `maintenance` (
  `id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `maintenance`
--

INSERT INTO `maintenance` (`id`, `facility_id`, `maintenance_type`, `description`, `status`, `priority`, `scheduled_date`, `completed_date`, `supervisor_id`, `notes`, `created_at`, `updated_at`) VALUES
(1, 4, 'REPAIR', '', 'completed', 'high', '2026-02-26', '2026-02-17', 4, NULL, '2026-02-17 08:29:48', '2026-02-17 16:01:50'),
(2, 2, 'REPAIR', '', 'completed', 'medium', '2026-02-19', '2026-02-17', 4, NULL, '2026-02-18 00:01:30', '2026-02-17 16:01:58'),
(3, 7, 'REPAIR', '', 'pending', 'medium', '2026-02-19', NULL, 4, NULL, '2026-02-18 00:01:37', '2026-02-17 16:01:37'),
(4, 2, 'REPAIR', '', 'in_progress', 'medium', '2026-02-08', NULL, 4, NULL, '2026-02-18 00:01:44', '2026-02-17 16:01:54');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `method` enum('online','walkin') NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `resort_name` varchar(255) NOT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `business_hours` varchar(255) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `resort_name`, `tagline`, `contact_info`, `business_hours`, `logo`, `updated_at`) VALUES
(1, 'Sinulom and Bolao', 'Cold Spring', '(example) 0917-123-4567', '8:00 AM - 5:00 PM', 'logo.jpg', '2026-02-10 04:43:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('owner','admin','frontdesk','supervisor') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `first_name`, `last_name`, `phone`, `address`, `status`, `created_at`, `updated_at`) VALUES
(1, 'owner', 'owner123', 'owner@resort.com', 'owner', 'Ziah', 'Bernice', '1234567890', '', 'active', '2026-02-05 23:27:38', '2026-02-24 15:30:47'),
(2, 'lin', 'lin123', 'lyap.bucod.coc@phinmaed.com', 'admin', 'Lyn', 'Bucod', '09122351650', NULL, 'active', '2026-02-05 23:54:06', '2026-02-24 15:16:41'),
(3, 'del', 'del123', 'joca.delfin.coc@phinmaed.com', 'frontdesk', 'Vohn Andre', 'Delfin', '09154684', NULL, 'active', '2026-02-05 23:54:35', '2026-02-24 15:29:53'),
(4, 'jus', 'jus123', 'jubu.toledo.coc@phinmaed.com', 'supervisor', 'Justin', 'Toledo', '0914686547', NULL, 'active', '2026-02-05 23:55:04', '2026-02-05 15:55:04'),
(5, 'sup', 'sup123', 'SADS@mail.com', 'supervisor', 'BERLYN', 'BUCOD', '11212121', NULL, 'inactive', '2026-02-10 16:08:01', '2026-02-10 08:08:06'),
(6, 'joshua', 'joshua123', 'joshua09242002@gmail.com', 'supervisor', 'Joshua Justin', 'Toledo', '', NULL, 'active', '2026-02-24 23:21:22', '2026-02-24 15:24:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `amenities`
--
ALTER TABLE `amenities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `areas`
--
ALTER TABLE `areas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `booking_addons`
--
ALTER TABLE `booking_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `facility_id` (`facility_id`),
  ADD KEY `amenity_id` (`amenity_id`);

--
-- Indexes for table `facilities`
--
ALTER TABLE `facilities`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `guest_otps`
--
ALTER TABLE `guest_otps`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `facility_id` (`facility_id`),
  ADD KEY `supervisor_id` (`supervisor_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `areas`
--
ALTER TABLE `areas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `booking_addons`
--
ALTER TABLE `booking_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `guest_otps`
--
ALTER TABLE `guest_otps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `maintenance`
--
ALTER TABLE `maintenance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `booking_addons`
--
ALTER TABLE `booking_addons`
  ADD CONSTRAINT `booking_addons_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`),
  ADD CONSTRAINT `booking_addons_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`),
  ADD CONSTRAINT `booking_addons_ibfk_3` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`);

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `maintenance`
--
ALTER TABLE `maintenance`
  ADD CONSTRAINT `maintenance_ibfk_1` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`),
  ADD CONSTRAINT `maintenance_ibfk_2` FOREIGN KEY (`supervisor_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

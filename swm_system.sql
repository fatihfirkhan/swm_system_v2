-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 02, 2026 at 05:53 PM
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
-- Database: `swm_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `collection_area`
--

CREATE TABLE `collection_area` (
  `area_id` int(11) NOT NULL,
  `taman_name` varchar(100) NOT NULL,
  `postcode` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `collection_area`
--

INSERT INTO `collection_area` (`area_id`, `taman_name`, `postcode`) VALUES
(1, 'Taman Sri Mutiara', '83300'),
(2, 'Taman Sri Gading', '83300'),
(3, 'Taman Pura Kencana', '83300');

-- --------------------------------------------------------

--
-- Table structure for table `collection_lane`
--

CREATE TABLE `collection_lane` (
  `lane_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `lane_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `collection_lane`
--

INSERT INTO `collection_lane` (`lane_id`, `area_id`, `lane_name`) VALUES
(1, 1, 'Jalan Sri Mutiara 1'),
(2, 1, 'Jalan Sri Mutiara 2'),
(3, 1, 'Jalan Sri Mutiara 3'),
(4, 1, 'Jalan Sri Mutiara 4'),
(5, 1, 'Jalan Sri Mutiara 5'),
(6, 1, 'Jalan Sri Mutiara 6'),
(7, 1, 'Jalan Sri Mutiara 7'),
(8, 1, 'Jalan Sri Mutiara 8'),
(9, 1, 'Jalan Sri Mutiara 9'),
(10, 2, 'Jalan Gading 1'),
(11, 2, 'Jalan Gading 2'),
(12, 2, 'Jalan Gading 3'),
(13, 2, 'Jalan Gading 4'),
(14, 2, 'Jalan Gading 5'),
(15, 2, 'Jalan Gading 6'),
(16, 2, 'Jalan Gading 7'),
(17, 2, 'Jalan Gading 8'),
(18, 2, 'Jalan Gading 9'),
(19, 2, 'Jalan Gading 10'),
(20, 2, 'Jalan Gading 11'),
(21, 2, 'Jalan Gading 12'),
(22, 3, 'Jalan Kencana 1A 1'),
(23, 3, 'Jalan Kencana 1A 2'),
(24, 3, 'Jalan Kencana 1A 3'),
(25, 3, 'Jalan Kencana 1A 4'),
(26, 3, 'Jalan Kencana 1A 5'),
(27, 3, 'Jalan Kencana 1A 6'),
(28, 3, 'Jalan Kencana 1A 7'),
(29, 3, 'Jalan Kencana 1A 8'),
(30, 3, 'Jalan Kencana 1A 9'),
(31, 3, 'Jalan Kencana 1A 10'),
(32, 3, 'Jalan Kencana 1A 11'),
(33, 3, 'Jalan Kencana 1A 12'),
(34, 3, 'Jalan Kencana 1A 13'),
(35, 3, 'Jalan Kencana 1A 14'),
(36, 3, 'Jalan Kencana 1A 15'),
(37, 3, 'Jalan Kencana 1A 16'),
(38, 3, 'Jalan Kencana 1A 17'),
(39, 3, 'Jalan Kencana 1A 18'),
(40, 3, 'Jalan Kencana 1A 19'),
(41, 3, 'Jalan Kencana 1A 20'),
(42, 3, 'Jalan Kencana 1A 21'),
(43, 3, 'Jalan Kencana 1A 22'),
(44, 3, 'Jalan Kencana 1A 23'),
(45, 3, 'Jalan Kencana 1A 24'),
(46, 3, 'Jalan Kencana 1A 25'),
(47, 3, 'Jalan Kencana 1A 26'),
(48, 3, 'Jalan Kencana 1A 27'),
(49, 3, 'Jalan Kencana 1B 1'),
(50, 3, 'Jalan Kencana 1B 2'),
(51, 3, 'Jalan Kencana 1B 3'),
(52, 3, 'Jalan Kencana 1B 4'),
(53, 3, 'Jalan Kencana 1B 5'),
(54, 3, 'Jalan Kencana 1B 6'),
(55, 3, 'Jalan Kencana 1B 7'),
(56, 3, 'Jalan Kencana 1B 8'),
(57, 3, 'Jalan Kencana 1B 9'),
(58, 3, 'Jalan Kencana 1B 10');

-- --------------------------------------------------------

--
-- Table structure for table `complaints`
--

CREATE TABLE `complaints` (
  `complaint_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `submission_time` datetime DEFAULT current_timestamp(),
  `status` enum('Pending','In Progress','Resolved') DEFAULT 'Pending',
  `admin_response` text DEFAULT NULL,
  `response_time` datetime DEFAULT NULL,
  `resident_feedback` text DEFAULT NULL,
  `rating` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `complaints`
--

INSERT INTO `complaints` (`complaint_id`, `user_id`, `area_id`, `description`, `image_url`, `submission_time`, `status`, `admin_response`, `response_time`, `resident_feedback`, `rating`) VALUES
(1, 18, 1, 'testing isu', NULL, '2025-12-22 15:58:06', 'Resolved', 'setel', '2025-12-22 16:02:01', 'mantap', 4),
(2, 18, 1, 'marah', NULL, '2025-12-22 16:02:48', 'In Progress', 'jap', NULL, NULL, NULL),
(3, 18, 1, 'test third time', 'uploads/complaints/complaint_18_1766409982.jpg', '2025-12-22 21:26:22', 'Resolved', 'okay setel', '2025-12-22 21:33:15', 'thankyou admin', NULL),
(4, 23, 1, 'sir', NULL, '2025-12-26 00:23:48', 'Resolved', 'done', '2025-12-26 09:00:11', NULL, NULL),
(5, 23, 1, 'Rubbish spilled on the road', NULL, '2025-12-26 08:56:41', 'Resolved', 'sampah telah dikutip pada 26 December 2025, jam 9.44 malam. Terima kasih kerana membuat laporan. mohon untuk beri feedback kepada servis kami', '2025-12-27 08:59:58', NULL, NULL),
(6, 23, 1, 'Waste not collected in my lane on today\'s schedule', NULL, '2025-12-26 08:58:44', 'In Progress', 'we will investigate the issue as soon as possible', NULL, NULL, NULL),
(7, 23, 1, 'Missed collection only at my house', NULL, '2025-12-26 21:40:06', 'Pending', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `lane_status`
--

CREATE TABLE `lane_status` (
  `lane_status_id` int(11) NOT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `lane_name` varchar(100) DEFAULT NULL,
  `status` enum('Pending','Collected') DEFAULT 'Pending',
  `updated_by` int(11) DEFAULT NULL,
  `update_time` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lane_status`
--

INSERT INTO `lane_status` (`lane_status_id`, `schedule_id`, `lane_name`, `status`, `updated_by`, `update_time`) VALUES
(1, 3, 'Jalan Sri Mutiara 1', 'Pending', 15, '2025-12-18 15:32:04'),
(2, 3, 'Jalan Sri Mutiara 2', 'Pending', 16, '2025-12-18 14:36:35'),
(3, 3, 'Jalan Sri Mutiara 3', 'Pending', 16, '2025-12-18 14:36:36'),
(4, 3, 'Jalan Sri Mutiara 4', 'Pending', 16, '2025-12-18 14:36:38'),
(5, 3, 'Jalan Sri Mutiara 5', 'Pending', 16, '2025-12-18 14:37:14');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `target_role` varchar(50) NOT NULL,
  `time_created` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `title`, `message`, `target_role`, `time_created`) VALUES
(1, 'Arahan Segera: Penyelenggaraan Lori', 'Semua pemandu zon A & B diminta membawa kenderaan ke bengkel pusat sebelum jam 5 petang ini untuk pemeriksaan brek berkala. Arahan pihak pengurusan.', 'Staff', '2025-12-12 07:27:41'),
(2, 'Gangguan Jadual Kutipan (Zon Melati)', 'Harap maklum, lori pengutip sampah mengalami kerosakan teknikal. Kutipan di kawasan Taman Melati akan lewat 2 jam dari jadual asal hari ini.', 'All', '2025-12-12 07:28:13'),
(3, 'Selamat Hari Pekerja & Cuti Umum', 'Pejabat operasi akan ditutup pada 1 Mei sempena Hari Pekerja. Walau bagaimanapun, operasi kutipan sampah berjalan seperti biasa mengikut jadual.', 'Resident', '2025-12-12 07:29:18'),
(9, 'Scheduled System Maintenance', 'The system will be undergoing scheduled maintenance on 26 Dec 2025 from 12:00 AM to 4:00 AM.', 'All', '2025-12-24 22:03:36'),
(10, 'Service Delay Notice', 'Due to heavy rain in the Sri Gading area, waste collection services today may be delayed by approximately 4 hours. We apologize for the inconvenience.', 'Resident', '2025-12-24 22:04:21'),
(11, 'Safety Equipment Reminder', 'Reminder to all field staff: High-visibility vests and safety boots must be worn at all times during operation. Spot checks will be conducted this week.', 'Staff', '2025-12-24 22:05:11'),
(12, 'Prohibited Waste Warning', 'Please do not dispose of construction waste, chemicals, or electronics in domestic bins. These require special bulk pickup requests via the system.', 'Resident', '2025-12-24 22:06:26'),
(13, 'Update Contact Info', 'To ensure you receive important alerts, please check your \"My Profile\" page and verify that your phone number and email address are up to date.', 'All', '2025-12-24 22:07:09');

-- --------------------------------------------------------

--
-- Table structure for table `notification_tracking`
--

CREATE TABLE `notification_tracking` (
  `id` int(11) NOT NULL,
  `user_id` varchar(50) NOT NULL,
  `last_check` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_tracking`
--

INSERT INTO `notification_tracking` (`id`, `user_id`, `last_check`) VALUES
(2, 'STA_STF007', '2025-12-18 20:58:59'),
(3, 'RES_18', '2026-01-02 20:12:29'),
(4, 'RES_21', '2025-12-12 07:32:45'),
(5, 'STA_STF005', '2026-01-02 20:22:49'),
(6, 'ADM_ADM001', '2026-01-02 20:38:59'),
(7, 'STA_STF001', '2025-12-18 21:00:08'),
(8, 'STA_STF004', '2025-12-22 14:33:09'),
(9, 'RES_23', '2025-12-26 15:11:45');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `schedule_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `collection_date` date DEFAULT NULL,
  `collection_type` enum('Domestic','Recycle') NOT NULL,
  `status` enum('Pending','Completed') DEFAULT 'Pending',
  `update_time` datetime DEFAULT current_timestamp(),
  `truck_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`schedule_id`, `area_id`, `collection_date`, `collection_type`, `status`, `update_time`, `truck_id`) VALUES
(1, 1, '2025-12-10', 'Recycle', 'Pending', '2025-12-09 10:37:48', 1),
(3, 1, '2025-12-18', 'Recycle', 'Pending', '2025-12-18 23:38:15', 1),
(6, 1, '2025-12-24', 'Domestic', 'Pending', '2025-12-22 14:31:30', 1),
(9, 1, '2025-12-29', 'Domestic', 'Pending', '2025-12-25 15:54:23', 1),
(10, 1, '2025-12-30', 'Domestic', 'Pending', '2025-12-25 15:54:42', 1),
(11, 1, '2025-12-27', 'Recycle', 'Pending', '2025-12-25 15:55:21', 3),
(12, 1, '2026-01-05', 'Domestic', 'Pending', '2025-12-25 16:13:07', 1),
(13, 1, '2026-01-06', 'Domestic', 'Pending', '2025-12-25 16:13:07', 1),
(14, 1, '2026-01-12', 'Domestic', 'Pending', '2025-12-25 16:13:07', 1),
(15, 1, '2026-01-13', 'Domestic', 'Pending', '2025-12-25 16:13:07', 1),
(16, 1, '2026-01-19', 'Domestic', 'Pending', '2025-12-25 16:13:07', 1),
(17, 1, '2026-01-20', 'Domestic', 'Pending', '2025-12-25 16:13:07', 1),
(18, 1, '2026-01-26', 'Domestic', 'Pending', '2025-12-25 16:13:07', 1),
(19, 1, '2026-01-27', 'Domestic', 'Pending', '2025-12-25 16:13:07', 1),
(20, 1, '2026-01-10', 'Recycle', 'Pending', '2025-12-25 16:24:35', 3),
(21, 1, '2026-01-03', 'Recycle', 'Pending', '2025-12-25 16:24:35', 3),
(22, 1, '2026-01-24', 'Recycle', 'Pending', '2025-12-25 16:24:35', 3),
(23, 1, '2026-01-17', 'Recycle', 'Pending', '2025-12-25 16:24:35', 3),
(24, 1, '2026-01-31', 'Recycle', 'Pending', '2025-12-25 16:24:35', 3);

-- --------------------------------------------------------

--
-- Table structure for table `truck`
--

CREATE TABLE `truck` (
  `truck_id` int(11) NOT NULL,
  `truck_number` varchar(50) NOT NULL,
  `capacity` varchar(50) DEFAULT NULL,
  `status` enum('active','maintenance','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `truck`
--

INSERT INTO `truck` (`truck_id`, `truck_number`, `capacity`, `status`, `created_at`) VALUES
(1, 'ABC123', '3-ton', 'active', '2025-12-07 02:54:38'),
(3, 'CDE234', '3-ton', 'active', '2025-12-07 03:13:46'),
(4, 'DEF321', NULL, 'active', '2025-12-25 05:13:49'),
(5, 'ABC234', NULL, 'active', '2025-12-25 05:14:20');

-- --------------------------------------------------------

--
-- Table structure for table `truck_staff`
--

CREATE TABLE `truck_staff` (
  `id` int(11) NOT NULL,
  `truck_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `truck_staff`
--

INSERT INTO `truck_staff` (`id`, `truck_id`, `user_id`, `role`, `status`, `created_at`) VALUES
(1, 1, 13, 'Collector 1', 'active', '2025-12-07 03:10:39'),
(2, 1, 11, 'Collector 1', 'inactive', '2025-12-07 03:10:39'),
(3, 1, 12, 'Collector 2', 'inactive', '2025-12-07 03:10:39'),
(4, 3, 15, 'Driver', 'inactive', '2025-12-07 03:48:29'),
(5, 3, 16, 'Collector 1', 'inactive', '2025-12-07 03:48:29'),
(6, 3, 14, 'Collector 2', 'active', '2025-12-07 03:48:29'),
(8, 1, 10, 'Collector 2', 'inactive', '2025-12-25 05:01:50'),
(9, 1, 16, 'Collector 2', 'active', '2025-12-25 05:02:14'),
(10, 3, 10, 'Collector 1', 'active', '2025-12-25 05:02:14'),
(11, 1, 15, 'Driver', 'active', '2025-12-25 05:03:52'),
(12, 3, 12, 'Driver', 'active', '2025-12-25 05:03:59');

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `house_unit_number` varchar(20) DEFAULT NULL,
  `lane_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `postcode` varchar(10) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `work_id` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('resident','staff','admin') NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`user_id`, `name`, `phone`, `house_unit_number`, `lane_id`, `area_id`, `address_line1`, `address_line2`, `postcode`, `email`, `work_id`, `password`, `role`, `created_at`) VALUES
(3, 'Fatih', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ADM001', '$2y$10$COVPEYBrwY/2n18psoZ6Jey.cFGewRlklTqIZip6AXJnUkpgXrOVO', 'admin', '2025-06-25 10:27:02'),
(10, 'Rahman bin Amin', '0114222315', NULL, NULL, NULL, '9, Jalan Kencana 10, Taman Pura Kencana', 'Sri Gading, Batu Pahat, Johor', '83300', NULL, 'STF001', '$2y$10$Fq9cOUXJFZdmguPRjGr5Fupi2axSmJVXmGYM8O89y5FWCS41II0ZC', 'staff', '2025-06-26 13:55:41'),
(11, 'Lim Chong Wey', '0120902311', NULL, NULL, NULL, '2, Jalan Kencana 3, Taman Pura Kencana', 'Sri Gading, Batu Pahat, Johor', '83300', NULL, 'STF002', '$2y$10$VgG3/xthusKobPcubmbM7enzLm8fzv/77OwBts8hn1.AxN/.vdi2a', 'staff', '2025-06-26 13:57:46'),
(12, 'Razli bin Sidek', '0174111311', NULL, NULL, NULL, '9, Jalan Kencana 2, Taman Pura Kencana', 'Sri Gading, Batu Pahat, Johor', '83300', NULL, 'STF003', '$2y$10$1WMz6i/F7tA/fNeoJha1GObSaYdwrF1TdZqzY9WKg./UettS.dzvO', 'staff', '2025-06-26 13:58:52'),
(13, 'Kamarul bin Adli', '0113332839', NULL, NULL, NULL, '10, Jalan Kencana 2, Taman Pura Kencana', 'Sri Gading, Batu Pahat, Johor', '83300', NULL, 'STF004', '$2y$10$GQcVpXdUBJwpQg6.KuCmKuoLYqk0gChf2.0krENeMK4poIfNMzRfu', 'staff', '2025-06-26 13:59:48'),
(14, 'Rami bin Malek', '0192100311', NULL, NULL, NULL, '9 Jalan Kencana 2, Taman Pura Kencana', 'Sri Gading, Batu Pahat, Johor', '83300', NULL, 'STF005', '$2y$10$FgaAnk/hHbvGlJ5Exp5keewf77eWKIVblWLVew0ZRSZYjD7xHrqaq', 'staff', '2025-12-07 11:46:10'),
(15, 'Christopher bin Nolang', '0114902437', NULL, NULL, NULL, '10 Jalan Kencana 2, Taman Pura Kencana', 'Sri Gading, Batu Pahat, Johor', '83300', NULL, 'STF006', '$2y$10$YZrxi.o1hK6ibWIlB84DRO.x/9lxVyK/dR8uysIGuD1/eAHgce2q.', 'staff', '2025-12-07 11:46:47'),
(16, 'Rayyan Kogler', '0174100322', NULL, NULL, NULL, '11, Jalan Kencana 2, Taman Pura Kencana', 'Sri Gading, Batu Pahat, Johor', '83300', NULL, 'STF007', '$2y$10$9Niip.xBuH8V35O3NHSw6OAungYOt/vWtSEwiKR/ScpQ1kOBcNuiO', 'staff', '2025-12-07 11:47:50'),
(18, 'Ali bin Abu', '0113002222', '6', 19, 2, '6, Jalan Gading 10, Taman Sri Gading', '', '83300', 'ali9000@gmail.com', NULL, '$2y$10$oT7/WvOf1ZErNHGQnWHa0Oz76IQ6EwTLYx.xUmOQGSE8jVPeWFipi', 'resident', '2025-12-09 10:56:15'),
(21, 'Zarouq bin Ahmad', '0113007777', '20', 3, 1, '20, Jalan Sri Mutiara 3, Taman Sri Mutiara', '', '83300', 'zarouq9000@gmail.com', NULL, '$2y$10$EuHBpklymPxODpZs0aSkPuhdxHsPifalffc/guO1P12x15Img0X6y', 'resident', '2025-12-10 23:57:39'),
(22, 'Wan Ariff bin Azhan', '0111007777', '6', 4, 1, '6, Jalan Sri Mutiara 4, Taman Sri Mutiara', '', '83300', 'wanarif@gmail.com', NULL, '$2y$10$t73MB3fJLe4.CxaWnz0T5udJKIOqtMEhsNfnQo.Frvftxidk4L/tu', 'resident', '2025-12-20 14:17:52'),
(23, 'Demo Account 1', '0123456789', '41', 6, 1, '41, Jalan Sri Mutiara 6, Taman Sri Mutiara', '', '83300', 'user@demo.com', NULL, '$2y$10$epDGnRB0ArNr1h9tpWUWbOw8.mzB8xrTQ146jXiQORjwtTk7e80R2', 'resident', '2025-12-24 13:18:53'),
(27, 'Dummy Resident 2', '01110011111', '3', 31, 3, '3, Jalan Kencana 1A 10, Taman Pura Kencana', '', '83300', 'user2@demo.com', NULL, '$2y$10$81M/gB5jIMkNg7WjT3lES.NbwlR1/wV8i9Kr0kQr.uOnMHlFKQNhi', 'resident', '2025-12-26 12:49:53');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `collection_area`
--
ALTER TABLE `collection_area`
  ADD PRIMARY KEY (`area_id`);

--
-- Indexes for table `collection_lane`
--
ALTER TABLE `collection_lane`
  ADD PRIMARY KEY (`lane_id`),
  ADD KEY `area_id` (`area_id`);

--
-- Indexes for table `complaints`
--
ALTER TABLE `complaints`
  ADD PRIMARY KEY (`complaint_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `area_id` (`area_id`);

--
-- Indexes for table `lane_status`
--
ALTER TABLE `lane_status`
  ADD PRIMARY KEY (`lane_status_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `notification_tracking`
--
ALTER TABLE `notification_tracking`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `area_id` (`area_id`),
  ADD KEY `fk_schedule_truck` (`truck_id`);

--
-- Indexes for table `truck`
--
ALTER TABLE `truck`
  ADD PRIMARY KEY (`truck_id`),
  ADD UNIQUE KEY `truck_number` (`truck_number`);

--
-- Indexes for table `truck_staff`
--
ALTER TABLE `truck_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_truck_staff` (`truck_id`,`user_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `fk_user_lane` (`lane_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `collection_area`
--
ALTER TABLE `collection_area`
  MODIFY `area_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `collection_lane`
--
ALTER TABLE `collection_lane`
  MODIFY `lane_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `complaints`
--
ALTER TABLE `complaints`
  MODIFY `complaint_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `lane_status`
--
ALTER TABLE `lane_status`
  MODIFY `lane_status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `notification_tracking`
--
ALTER TABLE `notification_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `truck`
--
ALTER TABLE `truck`
  MODIFY `truck_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `truck_staff`
--
ALTER TABLE `truck_staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `collection_lane`
--
ALTER TABLE `collection_lane`
  ADD CONSTRAINT `collection_lane_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `collection_area` (`area_id`) ON DELETE CASCADE;

--
-- Constraints for table `complaints`
--
ALTER TABLE `complaints`
  ADD CONSTRAINT `complaints_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`),
  ADD CONSTRAINT `complaints_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `collection_area` (`area_id`);

--
-- Constraints for table `lane_status`
--
ALTER TABLE `lane_status`
  ADD CONSTRAINT `lane_status_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`schedule_id`),
  ADD CONSTRAINT `lane_status_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `user` (`user_id`);

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `fk_schedule_truck` FOREIGN KEY (`truck_id`) REFERENCES `truck` (`truck_id`),
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`area_id`) REFERENCES `collection_area` (`area_id`);

--
-- Constraints for table `user`
--
ALTER TABLE `user`
  ADD CONSTRAINT `fk_user_lane` FOREIGN KEY (`lane_id`) REFERENCES `collection_lane` (`lane_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

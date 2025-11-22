-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 22, 2025 at 04:34 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `citycare_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `issues`
--

CREATE TABLE `issues` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `status` enum('open','in_progress','resolved') NOT NULL DEFAULT 'open',
  `location_text` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `photo_path` varchar(255) DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `issues`
--

INSERT INTO `issues` (`id`, `title`, `category`, `city`, `status`, `location_text`, `latitude`, `longitude`, `description`, `photo_path`, `is_anonymous`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'WOw', 'Streetlight', NULL, 'resolved', 'wsasddwad', NULL, NULL, 'asdsadsaw', 'uploads/issues/issue_20251122_105703_6074b6c5.png', 0, NULL, '2025-11-22 10:57:03', '2025-11-22 11:19:36'),
(2, 's', 'Streetlight', NULL, 'resolved', 's', NULL, NULL, 's', 'uploads/issues/issue_20251122_105717_d3c7cb94.png', 0, NULL, '2025-11-22 10:57:17', '2025-11-22 11:19:35'),
(3, 'sadasd', 'Streetlight', NULL, 'resolved', '222', 42.6630490, 21.1650410, 'trtr', 'uploads/issues/issue_20251122_110358_d8d0df6d.png', 0, NULL, '2025-11-22 11:03:58', '2025-11-22 11:19:35'),
(4, 'sad', 'Road / Pothole', NULL, 'resolved', '2', 42.6660030, 21.1704110, NULL, NULL, 0, NULL, '2025-11-22 11:08:11', '2025-11-22 11:19:35'),
(5, 'Report', 'Water / Sewage', NULL, 'resolved', NULL, 42.6629290, 21.1571680, 'It broke', 'uploads/issues/issue_20251122_111617_29f7f2a1.png', 0, NULL, '2025-11-22 11:16:17', '2025-11-22 11:19:34'),
(6, 'Leak', 'Water / Sewage', NULL, 'resolved', NULL, 42.6509050, 21.1747550, 'Leak since yesterday', 'uploads/issues/issue_20251122_113029_06d0bbf4.png', 0, NULL, '2025-11-22 11:30:29', '2025-11-22 11:32:15'),
(7, 'Leak', 'Water / Sewage', NULL, 'resolved', 'Rruga B', 42.6502420, 21.1736410, 'asdas', NULL, 0, NULL, '2025-11-22 11:34:16', '2025-11-22 11:36:37'),
(8, 'ssad', 'Road / Pothole', NULL, 'resolved', 'sad', 42.6613320, 21.1788180, 'asdad', NULL, 0, NULL, '2025-11-22 11:38:03', '2025-11-22 11:41:28'),
(9, 'sada', 'Road / Pothole', NULL, 'resolved', 'sdsa', 42.6555250, 21.1644060, 'sad', NULL, 0, NULL, '2025-11-22 11:47:20', '2025-11-22 11:50:40'),
(10, 'Work needed', 'Streetlight', NULL, 'in_progress', NULL, 42.6493140, 21.1389490, 'Need more lights', NULL, 0, NULL, '2025-11-22 13:02:33', '2025-11-22 13:05:10'),
(11, 'Broken fence', 'Other', NULL, 'open', NULL, 42.6595420, 21.1718730, NULL, NULL, 0, NULL, '2025-11-22 13:02:51', NULL),
(12, 'not enough trash', 'Waste / Trash', NULL, 'in_progress', NULL, 42.6509710, 21.1629620, 'More trashes.', NULL, 0, NULL, '2025-11-22 13:03:56', '2025-11-22 13:05:08'),
(13, 'Broken streetlight', 'Streetlight', NULL, 'open', NULL, 42.6613170, 21.1752840, NULL, NULL, 0, NULL, '2025-11-22 13:04:14', NULL),
(14, 'A lot of noise', 'Noise / Safety', NULL, 'open', NULL, 42.6482220, 21.1723530, NULL, NULL, 0, NULL, '2025-11-22 13:04:38', NULL),
(15, 'Bad roads', 'Road / Pothole', NULL, 'in_progress', NULL, 42.6372300, 21.1653020, 'Need fixing', NULL, 0, NULL, '2025-11-22 13:05:02', '2025-11-22 13:05:07'),
(16, 'asd', 'Road / Pothole', NULL, 'open', NULL, 42.9081470, 21.1941390, NULL, NULL, 0, NULL, '2025-11-22 13:42:02', NULL),
(17, 'Broken fence', 'Other', NULL, 'in_progress', NULL, 42.3747720, 20.4350260, NULL, 'uploads/issues/issue_20251122_134423_abea54a1.png', 0, NULL, '2025-11-22 13:44:23', '2025-11-22 13:45:01'),
(18, 'Broken Roads need fixing', 'Road / Pothole', NULL, 'open', NULL, 42.2096900, 20.7359770, NULL, NULL, 0, NULL, '2025-11-22 14:05:22', NULL),
(19, 'Broken', 'Waste / Trash', 'Prishtina', 'resolved', NULL, 42.6550710, 21.1642250, NULL, NULL, 0, NULL, '2025-11-22 14:13:21', '2025-11-22 14:17:36'),
(20, 'a', 'Streetlight', NULL, 'open', 'Prishtina', 42.6611810, 21.1674180, NULL, NULL, 0, NULL, '2025-11-22 14:23:13', NULL),
(21, 'Problem With Water', 'Water / Sewage', NULL, 'open', NULL, 42.6612660, 21.1668330, 'Yesterday it was working but today it is not!', NULL, 1, NULL, '2025-11-22 14:25:29', NULL),
(22, 'Testing Responsive', 'Waste / Trash', NULL, 'in_progress', NULL, 42.6533290, 21.1655010, 'Testing', 'uploads/issues/issue_20251122_143705_bbb925a7.png', 0, NULL, '2025-11-22 14:37:05', '2025-11-22 14:39:49'),
(23, 'sad', 'Road / Pothole', 'Prishtina', 'open', '=', 42.6615850, 21.1676660, 'asda', NULL, 0, 2, '2025-11-22 14:41:18', NULL),
(24, 'Activity', 'Road / Pothole', 'Prishtina', 'open', NULL, 42.6500210, 21.1635210, NULL, NULL, 0, 2, '2025-11-22 14:44:55', NULL),
(25, 'Needs working on road', 'Road / Pothole', 'Prishtina', 'in_progress', NULL, 42.6593130, 21.1340380, NULL, NULL, 0, 2, '2025-11-22 14:50:06', '2025-11-22 14:50:16'),
(26, 'broken lights', 'Streetlight', 'Rahovec', 'in_progress', NULL, 42.4001870, 20.6558950, NULL, NULL, 0, 10, '2025-11-22 14:57:39', '2025-11-22 14:58:13'),
(27, 'PROFILE NOT WORKING', 'Other', 'Rahovec', 'open', NULL, 42.6581640, 21.1552740, NULL, NULL, 0, 10, '2025-11-22 15:25:59', NULL),
(28, 'Just testing', 'Streetlight', 'Prishtina', 'resolved', NULL, 42.6842530, 21.1783260, NULL, NULL, 0, 2, '2025-11-22 15:51:11', '2025-11-22 16:04:30'),
(29, 'dren', 'Road / Pothole', 'Prizren', 'resolved', NULL, 42.2095150, 20.7455170, NULL, NULL, 0, 13, '2025-11-22 16:12:30', '2025-11-22 16:31:23'),
(30, 'sadsa', 'Streetlight', 'Prishtina', 'in_progress', NULL, 42.6613320, 21.1666370, NULL, NULL, 0, 2, '2025-11-22 16:31:03', '2025-11-22 16:31:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `city` varchar(100) DEFAULT NULL,
  `email` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `city`, `email`, `password_hash`, `is_admin`, `avatar`, `created_at`) VALUES
(1, 'Gerti Calaj', NULL, 'gerticalaj@gmail.com', '$2y$10$Xm/qdR678SOEEghkMAtaL.pJ.27NG8Sche1h4KBRpqfcMbJFcNGgO', 0, NULL, '2025-11-22 10:55:53'),
(2, 'Gerti Calaj', 'Prishtina', 'gerticalaj1@gmail.com', '$2y$10$9kMfLpZEcCIYG5chhncwaeikMswdu4gn8CcV95ffglHr48NcC0LXi', 1, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-22 11:48:46'),
(3, 'Gerti Calaj', 'Ferizaj', 'gerticalaj12@gmail.com', '$2y$10$g0kmsyTwAxE6EzjHn8gRF.F.3ywG/dquT3rjTG14e3EHGxfMw9Y1W', 0, NULL, '2025-11-22 12:10:30'),
(4, 'Gerti Calaj', 'Podujeva', 'gerticalaj222@gmail.com', '$2y$10$ImxivYgbEtaUgP2NjB08ROAb0sSOjs97vFx.xqk9uM8AvIBbTTEa6', 0, NULL, '2025-11-22 12:41:45'),
(5, 'Gerti Calaj', 'Gjakova', 'gerticalaj2222@gmail.com', '$2y$10$izCqxHJkvAoUdvdUP8GcQOdc0lU1gMQ/YUuc0fDiCpNzbnQYK9y52', 0, NULL, '2025-11-22 12:43:45'),
(6, 'Dren Gashi', 'Prizren', 'drengashi@gmail.com', '$2y$10$WPcxTulLSqq.RPatNNXM.uK/QY2.9/k8Uq.aIPWNZxweRUWECFEli', 0, NULL, '2025-11-22 13:04:48'),
(7, 'Gerti Calaj', 'Ferizaj', 'gerticalajds@gmail.com', '$2y$10$EuvJONJkJzxM6UJ8EFMzAeyG0.QecYqnhB8wpOmkvw8mi5zEXuL92', 0, NULL, '2025-11-22 13:14:19'),
(8, 'Gerti Calaj', 'Prishtina', 'gerticalaj123@gmail.com', '$2y$10$vtXRGESzUSozyUVbsVewDuDNtRP54yoqY6iBH.we2YsDeTNZtFQ42', 0, NULL, '2025-11-22 13:35:17'),
(9, 'Deon Beka', 'Prishtina', 'deonbeka@gmail.com', '$2y$10$yNoXer6KKS8r65MBeW4QseSDgTyKWLZP3T2hjORGsDF57P7dMe5G2', 0, NULL, '2025-11-22 13:38:54'),
(10, 'Amant Zabeli', 'Rahovec', 'amantzabeli@gmail.com', '$2y$10$LH5IeMXYmVFXew0lMLz62eb6XpHeceSo97Vbi9Dtz8UfoSkSsdtaG', 0, NULL, '2025-11-22 13:57:20'),
(11, 'Gerti Calaj', 'Prishtina', 'gerticalajds213123@gmail.com', '$2y$10$KGKPEc.ThBswj24FaEUx6OIHNX6JQYbGksGn82jeis3YzBPNFBs4G', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-22 14:53:07'),
(12, 'asdsa', 'Prishtina', 'asd@gmail.com', '$2y$10$yv/.NScOgZh3l.HMlJ63gu7IQD.S5.sIJdp9N9hHW8WZFzIIysV06', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-22 15:07:38'),
(13, 'Dren Gashi', 'Prizren', 'drengashi1@gmail.com', '$2y$10$GlfdTmmrgkMXjD5oBCAlteFLpIlRV6s1eewR886Kbp5y.EcN1aVr6', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Hipster_Walking/Hipster_Walking_Front.png', '2025-11-22 15:09:57'),
(14, 'Gerti Calaj', 'Prishtina', 'gerticalaj1332@gmail.com', '$2y$10$CtALZyQ.ZAZvLc8uxXhQ8e018oVIMZWoPH5haTkSJrFuwj7WsfWwi', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-22 15:32:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `issues`
--
ALTER TABLE `issues`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `issues`
--
ALTER TABLE `issues`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

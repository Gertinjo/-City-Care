-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 23, 2025 at 04:18 PM
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
  `handled_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `issues`
--

INSERT INTO `issues` (`id`, `title`, `category`, `city`, `status`, `location_text`, `latitude`, `longitude`, `description`, `photo_path`, `is_anonymous`, `created_by`, `handled_by`, `created_at`, `updated_at`) VALUES
(1, 'WOw', 'Streetlight', NULL, 'resolved', 'wsasddwad', NULL, NULL, 'asdsadsaw', 'uploads/issues/issue_20251122_105703_6074b6c5.png', 0, 0, NULL, '2025-11-22 10:57:03', '2025-11-23 11:47:55'),
(2, 's', 'Streetlight', NULL, 'resolved', 's', NULL, NULL, 's', 'uploads/issues/issue_20251122_105717_d3c7cb94.png', 0, NULL, NULL, '2025-11-22 10:57:17', '2025-11-22 11:19:35'),
(3, 'sadasd', 'Streetlight', NULL, 'resolved', '222', 42.6630490, 21.1650410, 'trtr', 'uploads/issues/issue_20251122_110358_d8d0df6d.png', 0, NULL, NULL, '2025-11-22 11:03:58', '2025-11-22 11:19:35'),
(4, 'sad', 'Road / Pothole', NULL, 'resolved', '2', 42.6660030, 21.1704110, NULL, NULL, 0, NULL, NULL, '2025-11-22 11:08:11', '2025-11-22 11:19:35'),
(5, 'Report', 'Water / Sewage', NULL, 'resolved', NULL, 42.6629290, 21.1571680, 'It broke', 'uploads/issues/issue_20251122_111617_29f7f2a1.png', 0, NULL, NULL, '2025-11-22 11:16:17', '2025-11-22 11:19:34'),
(6, 'Leak', 'Water / Sewage', NULL, 'resolved', NULL, 42.6509050, 21.1747550, 'Leak since yesterday', 'uploads/issues/issue_20251122_113029_06d0bbf4.png', 0, NULL, NULL, '2025-11-22 11:30:29', '2025-11-22 11:32:15'),
(7, 'Leak', 'Water / Sewage', NULL, 'resolved', 'Rruga B', 42.6502420, 21.1736410, 'asdas', NULL, 0, NULL, NULL, '2025-11-22 11:34:16', '2025-11-22 11:36:37'),
(8, 'ssad', 'Road / Pothole', NULL, 'resolved', 'sad', 42.6613320, 21.1788180, 'asdad', NULL, 0, NULL, NULL, '2025-11-22 11:38:03', '2025-11-22 11:41:28'),
(9, 'sada', 'Road / Pothole', NULL, 'resolved', 'sdsa', 42.6555250, 21.1644060, 'sad', NULL, 0, NULL, NULL, '2025-11-22 11:47:20', '2025-11-22 11:50:40'),
(10, 'Work needed', 'Streetlight', NULL, 'in_progress', NULL, 42.6493140, 21.1389490, 'Need more lights', NULL, 0, NULL, NULL, '2025-11-22 13:02:33', '2025-11-22 13:05:10'),
(11, 'Broken fence', 'Other', NULL, 'open', NULL, 42.6595420, 21.1718730, NULL, NULL, 0, NULL, NULL, '2025-11-22 13:02:51', NULL),
(12, 'not enough trash', 'Waste / Trash', NULL, 'in_progress', NULL, 42.6509710, 21.1629620, 'More trashes.', NULL, 0, NULL, NULL, '2025-11-22 13:03:56', '2025-11-22 13:05:08'),
(13, 'Broken streetlight', 'Streetlight', NULL, 'open', NULL, 42.6613170, 21.1752840, NULL, NULL, 0, NULL, NULL, '2025-11-22 13:04:14', NULL),
(14, 'A lot of noise', 'Noise / Safety', NULL, 'open', NULL, 42.6482220, 21.1723530, NULL, NULL, 0, NULL, NULL, '2025-11-22 13:04:38', NULL),
(15, 'Bad roads', 'Road / Pothole', NULL, 'in_progress', NULL, 42.6372300, 21.1653020, 'Need fixing', NULL, 0, NULL, NULL, '2025-11-22 13:05:02', '2025-11-22 13:05:07'),
(16, 'asd', 'Road / Pothole', NULL, 'open', NULL, 42.9081470, 21.1941390, NULL, NULL, 0, NULL, NULL, '2025-11-22 13:42:02', NULL),
(17, 'Broken fence', 'Other', NULL, 'in_progress', NULL, 42.3747720, 20.4350260, NULL, 'uploads/issues/issue_20251122_134423_abea54a1.png', 0, NULL, NULL, '2025-11-22 13:44:23', '2025-11-22 13:45:01'),
(18, 'Broken Roads need fixing', 'Road / Pothole', NULL, 'open', NULL, 42.2096900, 20.7359770, NULL, NULL, 0, NULL, NULL, '2025-11-22 14:05:22', NULL),
(19, 'Broken', 'Waste / Trash', 'Prishtina', 'resolved', NULL, 42.6550710, 21.1642250, NULL, NULL, 0, NULL, NULL, '2025-11-22 14:13:21', '2025-11-22 14:17:36'),
(20, 'a', 'Streetlight', NULL, 'open', 'Prishtina', 42.6611810, 21.1674180, NULL, NULL, 0, NULL, NULL, '2025-11-22 14:23:13', NULL),
(21, 'Problem With Water', 'Water / Sewage', NULL, 'open', NULL, 42.6612660, 21.1668330, 'Yesterday it was working but today it is not!', NULL, 1, NULL, NULL, '2025-11-22 14:25:29', NULL),
(22, 'Testing Responsive', 'Waste / Trash', NULL, 'in_progress', NULL, 42.6533290, 21.1655010, 'Testing', 'uploads/issues/issue_20251122_143705_bbb925a7.png', 0, NULL, NULL, '2025-11-22 14:37:05', '2025-11-22 14:39:49'),
(23, 'sad', 'Road / Pothole', 'Prishtina', 'open', '=', 42.6615850, 21.1676660, 'asda', NULL, 0, 2, NULL, '2025-11-22 14:41:18', NULL),
(24, 'Activity', 'Road / Pothole', 'Prishtina', 'open', NULL, 42.6500210, 21.1635210, NULL, NULL, 0, 2, NULL, '2025-11-22 14:44:55', NULL),
(25, 'Needs working on road', 'Road / Pothole', 'Prishtina', 'in_progress', NULL, 42.6593130, 21.1340380, NULL, NULL, 0, 2, NULL, '2025-11-22 14:50:06', '2025-11-22 14:50:16'),
(26, 'broken lights', 'Streetlight', 'Rahovec', 'resolved', NULL, 42.4001870, 20.6558950, NULL, NULL, 0, 10, NULL, '2025-11-22 14:57:39', '2025-11-23 16:17:13'),
(27, 'PROFILE NOT WORKING', 'Other', 'Rahovec', 'resolved', NULL, 42.6581640, 21.1552740, NULL, NULL, 0, 10, NULL, '2025-11-22 15:25:59', '2025-11-23 16:17:14'),
(28, 'Just testing', 'Streetlight', 'Prishtina', 'resolved', NULL, 42.6842530, 21.1783260, NULL, NULL, 0, 2, NULL, '2025-11-22 15:51:11', '2025-11-22 16:04:30'),
(29, 'dren', 'Road / Pothole', 'Prizren', 'resolved', NULL, 42.2095150, 20.7455170, NULL, NULL, 0, 13, NULL, '2025-11-22 16:12:30', '2025-11-22 16:31:23'),
(30, 'sadsa', 'Streetlight', 'Prishtina', 'in_progress', NULL, 42.6613320, 21.1666370, NULL, NULL, 0, 2, NULL, '2025-11-22 16:31:03', '2025-11-22 16:31:19'),
(31, 'Ska drit', 'Streetlight', 'Prishtina', 'resolved', NULL, 42.6484680, 21.1723370, NULL, NULL, 0, 15, NULL, '2025-11-22 17:10:42', '2025-11-22 17:15:52'),
(32, 'PotHole', 'Road / Pothole', 'Prishtina', 'in_progress', NULL, 42.6431900, 21.1662040, NULL, 'uploads/issues/issue_20251123_111249_0e655b1a.jpg', 0, 2, 2, '2025-11-23 11:12:49', '2025-11-23 13:58:02'),
(33, 'asda', 'Streetlight', 'Prishtina', 'in_progress', NULL, 42.6548940, 21.1638920, NULL, 'uploads/issues/issue_20251123_111744_788c96c1.jpg', 0, 2, NULL, '2025-11-23 11:17:44', '2025-11-23 11:18:23'),
(34, 'asd', 'Road / Pothole', 'Prishtina', 'in_progress', NULL, 42.6542630, 21.1690390, NULL, 'uploads/issues/issue_20251123_114857_61cd13ad.jpg', 0, 2, NULL, '2025-11-23 11:48:57', '2025-11-23 11:49:21'),
(35, 'sad', 'Streetlight', 'Ferizaj', 'resolved', NULL, 42.3668900, 21.1609700, NULL, 'uploads/issues/issue_20251123_115212_a7f43b76.jpg', 0, 7, NULL, '2025-11-23 11:52:12', '2025-11-23 12:41:08'),
(36, 'Testing', 'Other', 'Ferizaj', 'resolved', NULL, 42.3651140, 21.1571950, NULL, 'uploads/issues/issue_20251123_125606_6023857c.jpg', 0, 7, 7, '2025-11-23 12:56:06', '2025-11-23 13:02:42'),
(37, 'wasd', 'Road / Pothole', 'Ferizaj', 'resolved', NULL, 42.3770360, 21.1568520, NULL, 'uploads/issues/issue_20251123_133433_4d63f303.jpg', 0, 7, 7, '2025-11-23 13:34:33', '2025-11-23 14:00:05'),
(38, 'asd', 'Streetlight', 'Prishtina', 'in_progress', NULL, 42.6546670, 21.1656070, NULL, 'uploads/issues/issue_20251123_134415_ceae51f6.jpg', 1, 2, 2, '2025-11-23 13:44:15', '2025-11-23 14:05:29'),
(39, 'pothole', 'Road / Pothole', 'Prishtina', 'in_progress', NULL, 42.6553990, 21.1637200, NULL, 'uploads/issues/issue_20251123_134715_7140a6b7.jpg', 0, 23, 2, '2025-11-23 13:47:15', '2025-11-23 14:05:28'),
(40, 'asda', 'fasdsa', 'Prishtina', 'open', '42.655020, 21.171877', 42.6550200, 21.1718770, NULL, 'uploads/issues/issue_20251123_142908_d9cb6b75.jpg', 0, 2, NULL, '2025-11-23 14:29:09', NULL),
(41, 'asdasdsad', 'Broken Fence', 'Prishtina', 'in_progress', '42.660323, 21.165380', 42.6603230, 21.1653800, NULL, 'uploads/issues/issue_20251123_145532_0eca807b.jpg', 0, 2, 2, '2025-11-23 14:55:32', '2025-11-23 14:56:11'),
(42, 'asedas', 'ads', 'Ferizaj', 'resolved', '42.348533, 21.177000', 42.3485330, 21.1770000, NULL, 'uploads/issues/issue_20251123_152942_1137ff6d.jpg', 0, 7, 7, '2025-11-23 15:29:42', '2025-11-23 16:15:34'),
(43, 'sad', 'Streetlight', 'Rahovec', 'resolved', '42.659313, 21.163377', 42.6593130, 21.1633770, NULL, 'uploads/issues/issue_20251123_161611_f35af06b.jpg', 0, 10, 10, '2025-11-23 16:16:11', '2025-11-23 16:18:07'),
(44, 'sadsa', 'Streetlight', 'Rahovec', 'resolved', '42.654616, 21.161258', 42.6546160, 21.1612580, NULL, 'uploads/issues/issue_20251123_161620_d7c3baa4.jpg', 0, 10, 10, '2025-11-23 16:16:20', '2025-11-23 16:18:07'),
(45, 'asdsa', 'Streetlight', 'Rahovec', 'resolved', '42.660196, 21.163377', 42.6601960, 21.1633770, NULL, 'uploads/issues/issue_20251123_161629_dc245e91.jpg', 0, 10, 10, '2025-11-23 16:16:29', '2025-11-23 16:18:06'),
(46, 'asdsa', 'asdsa', 'Rahovec', 'resolved', '42.655980, 21.166636', 42.6559800, 21.1666360, NULL, 'uploads/issues/issue_20251123_161638_0bae0d8d.jpg', 0, 10, 10, '2025-11-23 16:16:38', '2025-11-23 16:18:05');

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
(1, 'Gerti Calaj', 'Prishtina', 'gerticalaj@gmail.com', '$2y$10$Xm/qdR678SOEEghkMAtaL.pJ.27NG8Sche1h4KBRpqfcMbJFcNGgO', 0, NULL, '2025-11-22 10:55:53'),
(2, 'Gerti Calaj', 'Prishtina', 'gerticalaj1@gmail.com', '$2y$10$9kMfLpZEcCIYG5chhncwaeikMswdu4gn8CcV95ffglHr48NcC0LXi', 2, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-22 11:48:46'),
(3, 'Gerti Calaj', 'Ferizaj', 'gerticalaj12@gmail.com', '$2y$10$g0kmsyTwAxE6EzjHn8gRF.F.3ywG/dquT3rjTG14e3EHGxfMw9Y1W', 0, NULL, '2025-11-22 12:10:30'),
(4, 'Gerti Calaj', 'Podujeva', 'gerticalaj222@gmail.com', '$2y$10$ImxivYgbEtaUgP2NjB08ROAb0sSOjs97vFx.xqk9uM8AvIBbTTEa6', 0, NULL, '2025-11-22 12:41:45'),
(5, 'Gerti Calaj', 'Gjakova', 'gerticalaj2222@gmail.com', '$2y$10$izCqxHJkvAoUdvdUP8GcQOdc0lU1gMQ/YUuc0fDiCpNzbnQYK9y52', 0, NULL, '2025-11-22 12:43:45'),
(6, 'Dren Gashi', 'Prizren', 'drengashi@gmail.com', '$2y$10$WPcxTulLSqq.RPatNNXM.uK/QY2.9/k8Uq.aIPWNZxweRUWECFEli', 0, NULL, '2025-11-22 13:04:48'),
(7, 'Gerti Calaj', 'Ferizaj', 'gerticalajds@gmail.com', '$2y$10$EuvJONJkJzxM6UJ8EFMzAeyG0.QecYqnhB8wpOmkvw8mi5zEXuL92', 1, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-22 13:14:19'),
(8, 'Gerti Calaj', 'Prishtina', 'gerticalaj123@gmail.com', '$2y$10$vtXRGESzUSozyUVbsVewDuDNtRP54yoqY6iBH.we2YsDeTNZtFQ42', 0, NULL, '2025-11-22 13:35:17'),
(9, 'Deon Beka', 'Prishtina', 'deonbeka@gmail.com', '$2y$10$yNoXer6KKS8r65MBeW4QseSDgTyKWLZP3T2hjORGsDF57P7dMe5G2', 0, NULL, '2025-11-22 13:38:54'),
(10, 'Amant Zabeli', 'Rahovec', 'amantzabeli@gmail.com', '$2y$10$LH5IeMXYmVFXew0lMLz62eb6XpHeceSo97Vbi9Dtz8UfoSkSsdtaG', 1, NULL, '2025-11-22 13:57:20'),
(11, 'Gerti Calaj', 'Prishtina', 'gerticalajds213123@gmail.com', '$2y$10$KGKPEc.ThBswj24FaEUx6OIHNX6JQYbGksGn82jeis3YzBPNFBs4G', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-22 14:53:07'),
(12, 'asdsa', 'Prishtina', 'asd@gmail.com', '$2y$10$yv/.NScOgZh3l.HMlJ63gu7IQD.S5.sIJdp9N9hHW8WZFzIIysV06', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-22 15:07:38'),
(13, 'Dren Gashi', 'Prizren', 'drengashi1@gmail.com', '$2y$10$GlfdTmmrgkMXjD5oBCAlteFLpIlRV6s1eewR886Kbp5y.EcN1aVr6', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Hipster_Walking/Hipster_Walking_Front.png', '2025-11-22 15:09:57'),
(14, 'Gerti Calaj', 'Prishtina', 'gerticalaj1332@gmail.com', '$2y$10$CtALZyQ.ZAZvLc8uxXhQ8e018oVIMZWoPH5haTkSJrFuwj7WsfWwi', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-22 15:32:55'),
(15, 'Dafina Calaj', 'Prishtina', 'dafina@gmail.com', '$2y$10$idINKf4/4VYzxWYvZ/0Lh.Pz22Pk0zFRAMEf1KBit1sD96L7qyi2a', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Shadow_Walking/Shadow_Walking_Front.png', '2025-11-22 16:09:51'),
(16, 'asdsads', 'Prishtina', 'asdasd@gmail.com', '$2y$10$rsT80dRYHHnNfmLhxisO4.y6uaiXSr2fpA58VBhmoG2f8jeDtMGiK', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Shadow_Walking/Shadow_Walking_Front.png', '2025-11-22 16:14:42'),
(17, 'Amra Calaj', 'Prishtina', 'amracalaj@gmail.com', '$2y$10$32OCgPuMyN1DO24uhPCuA.6bjmZzNF1ziU42hu5fXXQ9yHbLisUqG', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Speedster_Walking/Speedster_Walking_Front.png', '2025-11-22 16:17:50'),
(18, 'Gerti Calaj', 'Prishtina', 'gerticalajd1s@gmail.com', '$2y$10$W82rDv8LmreOp7x4nCXUCOnLoMnW4lHCGoen9kW/25JbAXokF3Gxu', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Engineer_Walking/Engineer_Walking_Front.png', '2025-11-23 09:09:04'),
(19, 'Gerti calaj', 'Ferizaj', 'erticalaj1123123@gmail.com', '$2y$10$nRQdZfgWw6mNACbXQHWEme93iT7kvy2iWU5RlC/p2n0CAk2/ee0X.', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Hipster_Walking/Hipster_Walking_Front.png', '2025-11-23 10:00:23'),
(20, 'Gerti Cala', 'Prishtina', 'gerticalaj11233@gmail.com', '$2y$10$rkUPb.57B99gYfY9gFi8COiP/ZCsUV9s3ajETJARcznlX1zGslX0.', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Hipster_Walking/Hipster_Walking_Front.png', '2025-11-23 10:16:19'),
(21, 'Amar Bytyqi', 'Prishtina', 'amarbytyqi@gmail.com', '$2y$10$An/EX.MMZYVtgW5Cky24POaNfHMeAfOHuqELaEqkb2DLJb1jL/.KG', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Hipster_Walking/Hipster_Walking_Front.png', '2025-11-23 11:59:04'),
(22, 'asda', 'Podujeva', 'gerticalaj1222@gmail.com', '$2y$10$PnD9JRPsYqdpHJrk5Tt1PO08eV1kiwm/3XUzDurYlPrDDyiyAeOwW', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Speedster_Walking/Speedster_Walking_Front.png', '2025-11-23 12:39:21'),
(23, 'Gerti Calaj', 'Ferizaj', 'gerticalaj1123123@gmail.com', '$2y$10$DrEgxwP0cUpKJ9ysVRaJU.SbvHba53MxDxBEP643cwYV5k3.QSs7q', 0, '/-CITY-CARE/City-Main/uploads/Walking_PNGs/Shadow_Walking/Shadow_Walking_Front.png', '2025-11-23 12:46:27');

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

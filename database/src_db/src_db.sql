-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 01, 2025 at 04:15 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `src_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_year`
--

CREATE TABLE `academic_year` (
  `ay_id` int(11) NOT NULL,
  `ay_name` varchar(50) NOT NULL,
  `date_start` date NOT NULL,
  `date_end` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_year`
--

INSERT INTO `academic_year` (`ay_id`, `ay_name`, `date_start`, `date_end`) VALUES
(1, '2024-2025', '0000-00-00', '0000-00-00');

-- --------------------------------------------------------

--
-- Table structure for table `admission`
--

CREATE TABLE `admission` (
  `admission_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `academic_year_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `year_level_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admission`
--

INSERT INTO `admission` (`admission_id`, `student_id`, `academic_year_id`, `semester_id`, `section_id`, `year_level_id`, `course_id`, `subject_id`, `schedule_id`) VALUES
(24, '23-0003060', 1, 1, 5, 3, 1, NULL, NULL),
(25, '22-0003082', 1, 1, 5, 3, 1, NULL, NULL),
(26, '22-0002676', 1, 1, 5, 3, 1, NULL, NULL),
(27, '23-0003054', 1, 1, 5, 3, 1, NULL, NULL),
(28, '23-0003063', 1, 1, 5, 3, 1, NULL, NULL),
(29, '23-0003011', 1, 1, 5, 3, 1, NULL, NULL),
(30, '23-0003022', 1, 1, 5, 3, 1, NULL, NULL),
(31, '22-0002438', 1, 1, 5, 3, 1, NULL, NULL),
(32, '23-0003062', 1, 1, 5, 3, 1, NULL, NULL),
(33, '22-0002686', 1, 1, 5, 3, 1, NULL, NULL),
(34, '22-0001559', 1, 1, 5, 3, 1, NULL, NULL),
(35, '23-0003053', 1, 1, 5, 3, 1, NULL, NULL),
(36, '23-0003087', 1, 1, 5, 3, 1, NULL, NULL),
(37, '24-0003174', 1, 1, 5, 3, 1, NULL, NULL),
(38, '23-0003167', 1, 1, 5, 3, 1, NULL, NULL),
(39, '23-0003005', 1, 1, 5, 3, 1, NULL, NULL),
(40, '23-0003103', 1, 1, 5, 3, 1, NULL, NULL),
(41, '23-0003023', 1, 1, 5, 3, 1, NULL, NULL),
(42, '23-0003108', 1, 1, 5, 3, 1, NULL, NULL),
(43, '22-0002534', 1, 1, 5, 3, 1, NULL, NULL),
(44, '23-0003028', 1, 1, 5, 3, 1, NULL, NULL),
(45, '23-0003098', 1, 1, 5, 3, 1, NULL, NULL),
(46, '22-0002360', 1, 1, 5, 3, 1, NULL, NULL),
(47, '23-0003012', 1, 1, 5, 3, 1, NULL, NULL),
(48, '23-0003034', 1, 1, 5, 3, 1, NULL, NULL),
(49, '23-0003026', 1, 1, 5, 3, 1, NULL, NULL),
(206, '22-0002822', 1, 1, 3, 2, 1, NULL, NULL),
(207, '24-0003256', 1, 1, 3, 2, 1, NULL, NULL),
(208, '24-0003321', 1, 1, 3, 2, 1, NULL, NULL),
(209, '24-0003261', 1, 1, 3, 2, 1, NULL, NULL),
(210, '24-0003349', 1, 1, 3, 2, 1, NULL, NULL),
(211, '24-0003435', 1, 1, 3, 2, 1, NULL, NULL),
(212, '24-0003425', 1, 1, 3, 2, 1, NULL, NULL),
(213, '24-0003308', 1, 1, 3, 2, 1, NULL, NULL),
(214, '24-0003343', 1, 1, 3, 2, 1, NULL, NULL),
(215, '24 -000326', 1, 1, 3, 2, 1, NULL, NULL),
(216, '24-0003307', 1, 1, 3, 2, 1, NULL, NULL),
(217, '24-0003280', 1, 1, 3, 2, 1, NULL, NULL),
(218, '24-0003414', 1, 1, 3, 2, 1, NULL, NULL),
(219, '24-0003331', 1, 1, 3, 2, 1, NULL, NULL),
(220, '24-0003290', 1, 1, 3, 2, 1, NULL, NULL),
(221, '22-0002391', 1, 1, 3, 2, 1, NULL, NULL),
(222, '25-0003781', 1, 1, 3, 2, 1, NULL, NULL),
(223, '24-0003262', 1, 1, 3, 2, 1, NULL, NULL),
(224, '24-0003315', 1, 1, 3, 2, 1, NULL, NULL),
(225, '24-0003292', 1, 1, 3, 2, 1, NULL, NULL),
(226, '24-0003306', 1, 1, 3, 2, 1, NULL, NULL),
(227, '24-0003410', 1, 1, 3, 2, 1, NULL, NULL),
(228, '24-0003318', 1, 1, 3, 2, 1, NULL, NULL),
(229, '25-0003447', 1, 1, 3, 2, 1, NULL, NULL),
(230, '24-0003426', 1, 1, 3, 2, 1, NULL, NULL),
(231, '23-0003060', 1, 2, 1, 1, 1, 18, 16),
(232, '22-0003082', 1, 2, 1, 1, 1, 18, 16),
(233, '22-0002676', 1, 2, 1, 1, 1, 18, 16),
(234, '23-0003054', 1, 2, 1, 1, 1, 18, 16),
(235, '23-0003063', 1, 2, 1, 1, 1, 18, 16),
(236, '23-0003011', 1, 2, 1, 1, 1, 18, 16),
(237, '23-0003022', 1, 2, 1, 1, 1, 18, 16),
(238, '22-0002438', 1, 2, 1, 1, 1, 18, 16),
(239, '23-0003062', 1, 2, 1, 1, 1, 18, 16),
(240, '22-0002686', 1, 2, 1, 1, 1, 18, 16),
(241, '22-0001559', 1, 2, 1, 1, 1, 18, 16),
(242, '23-0003053', 1, 2, 1, 1, 1, 18, 16),
(243, '23-0003087', 1, 2, 1, 1, 1, 18, 16),
(244, '24-0003174', 1, 2, 1, 1, 1, 18, 16),
(245, '23-0003167', 1, 2, 1, 1, 1, 18, 16),
(246, '23-0003005', 1, 2, 1, 1, 1, 18, 16),
(247, '23-0003103', 1, 2, 1, 1, 1, 18, 16),
(248, '23-0003023', 1, 2, 1, 1, 1, 18, 16),
(249, '23-0003108', 1, 2, 1, 1, 1, 18, 16),
(250, '22-0002534', 1, 2, 1, 1, 1, 18, 16),
(251, '23-0003028', 1, 2, 1, 1, 1, 18, 16),
(252, '23-0003098', 1, 2, 1, 1, 1, 18, 16),
(253, '22-0002360', 1, 2, 1, 1, 1, 18, 16),
(254, '23-0003012', 1, 2, 1, 1, 1, 18, 16),
(255, '23-0003034', 1, 2, 1, 1, 1, 18, 16),
(256, '23-0003026', 1, 2, 1, 1, 1, 18, 16),
(257, '23-0003060', 1, 2, 1, 1, 1, 19, 17),
(258, '22-0003082', 1, 2, 1, 1, 1, 19, 17),
(259, '22-0002676', 1, 2, 1, 1, 1, 19, 17),
(260, '23-0003054', 1, 2, 1, 1, 1, 19, 17),
(261, '23-0003063', 1, 2, 1, 1, 1, 19, 17),
(262, '23-0003011', 1, 2, 1, 1, 1, 19, 17),
(263, '23-0003022', 1, 2, 1, 1, 1, 19, 17),
(264, '22-0002438', 1, 2, 1, 1, 1, 19, 17),
(265, '23-0003062', 1, 2, 1, 1, 1, 19, 17),
(266, '22-0002686', 1, 2, 1, 1, 1, 19, 17),
(267, '22-0001559', 1, 2, 1, 1, 1, 19, 17),
(268, '23-0003053', 1, 2, 1, 1, 1, 19, 17),
(269, '23-0003087', 1, 2, 1, 1, 1, 19, 17),
(270, '24-0003174', 1, 2, 1, 1, 1, 19, 17),
(271, '23-0003167', 1, 2, 1, 1, 1, 19, 17),
(272, '23-0003005', 1, 2, 1, 1, 1, 19, 17),
(273, '23-0003103', 1, 2, 1, 1, 1, 19, 17),
(274, '23-0003023', 1, 2, 1, 1, 1, 19, 17),
(275, '23-0003108', 1, 2, 1, 1, 1, 19, 17),
(276, '22-0002534', 1, 2, 1, 1, 1, 19, 17),
(277, '23-0003028', 1, 2, 1, 1, 1, 19, 17),
(278, '23-0003098', 1, 2, 1, 1, 1, 19, 17),
(279, '22-0002360', 1, 2, 1, 1, 1, 19, 17),
(280, '23-0003012', 1, 2, 1, 1, 1, 19, 17),
(281, '23-0003034', 1, 2, 1, 1, 1, 19, 17),
(282, '23-0003026', 1, 2, 1, 1, 1, 19, 17),
(283, '23-0003060', 1, 2, 1, 1, 1, 24, 22),
(284, '22-0003082', 1, 2, 1, 1, 1, 24, 22),
(285, '22-0002676', 1, 2, 1, 1, 1, 24, 22),
(286, '23-0003054', 1, 2, 1, 1, 1, 24, 22),
(287, '23-0003063', 1, 2, 1, 1, 1, 24, 22),
(288, '23-0003011', 1, 2, 1, 1, 1, 24, 22),
(289, '23-0003022', 1, 2, 1, 1, 1, 24, 22),
(290, '22-0002438', 1, 2, 1, 1, 1, 24, 22),
(291, '23-0003062', 1, 2, 1, 1, 1, 24, 22),
(292, '22-0002686', 1, 2, 1, 1, 1, 24, 22),
(293, '22-0001559', 1, 2, 1, 1, 1, 24, 22),
(294, '23-0003053', 1, 2, 1, 1, 1, 24, 22),
(295, '23-0003087', 1, 2, 1, 1, 1, 24, 22),
(296, '24-0003174', 1, 2, 1, 1, 1, 24, 22),
(297, '23-0003167', 1, 2, 1, 1, 1, 24, 22),
(298, '23-0003005', 1, 2, 1, 1, 1, 24, 22),
(299, '23-0003103', 1, 2, 1, 1, 1, 24, 22),
(300, '23-0003023', 1, 2, 1, 1, 1, 24, 22),
(301, '23-0003108', 1, 2, 1, 1, 1, 24, 22),
(302, '22-0002534', 1, 2, 1, 1, 1, 24, 22),
(303, '23-0003028', 1, 2, 1, 1, 1, 24, 22),
(304, '23-0003098', 1, 2, 1, 1, 1, 24, 22),
(305, '22-0002360', 1, 2, 1, 1, 1, 24, 22),
(306, '23-0003012', 1, 2, 1, 1, 1, 24, 22),
(307, '23-0003034', 1, 2, 1, 1, 1, 24, 22),
(308, '23-0003026', 1, 2, 1, 1, 1, 24, 22),
(309, '23-0003060', 1, 2, 1, 1, 1, 29, 27),
(310, '22-0003082', 1, 2, 1, 1, 1, 29, 27),
(311, '22-0002676', 1, 2, 1, 1, 1, 29, 27),
(312, '23-0003054', 1, 2, 1, 1, 1, 29, 27),
(313, '23-0003063', 1, 2, 1, 1, 1, 29, 27),
(314, '23-0003011', 1, 2, 1, 1, 1, 29, 27),
(315, '23-0003022', 1, 2, 1, 1, 1, 29, 27),
(316, '22-0002438', 1, 2, 1, 1, 1, 29, 27),
(317, '23-0003062', 1, 2, 1, 1, 1, 29, 27),
(318, '22-0002686', 1, 2, 1, 1, 1, 29, 27),
(319, '22-0001559', 1, 2, 1, 1, 1, 29, 27),
(320, '23-0003053', 1, 2, 1, 1, 1, 29, 27),
(321, '23-0003087', 1, 2, 1, 1, 1, 29, 27),
(322, '24-0003174', 1, 2, 1, 1, 1, 29, 27),
(323, '23-0003167', 1, 2, 1, 1, 1, 29, 27),
(324, '23-0003005', 1, 2, 1, 1, 1, 29, 27),
(325, '23-0003103', 1, 2, 1, 1, 1, 29, 27),
(326, '23-0003023', 1, 2, 1, 1, 1, 29, 27),
(327, '23-0003108', 1, 2, 1, 1, 1, 29, 27),
(328, '22-0002534', 1, 2, 1, 1, 1, 29, 27),
(329, '23-0003028', 1, 2, 1, 1, 1, 29, 27),
(330, '23-0003098', 1, 2, 1, 1, 1, 29, 27),
(331, '22-0002360', 1, 2, 1, 1, 1, 29, 27),
(332, '23-0003012', 1, 2, 1, 1, 1, 29, 27),
(333, '23-0003034', 1, 2, 1, 1, 1, 29, 27),
(334, '23-0003026', 1, 2, 1, 1, 1, 29, 27),
(335, '23-0003060', 1, 2, 1, 1, 1, 27, 25),
(336, '22-0003082', 1, 2, 1, 1, 1, 27, 25),
(337, '22-0002676', 1, 2, 1, 1, 1, 27, 25),
(338, '23-0003054', 1, 2, 1, 1, 1, 27, 25),
(339, '23-0003063', 1, 2, 1, 1, 1, 27, 25),
(340, '23-0003011', 1, 2, 1, 1, 1, 27, 25),
(341, '23-0003022', 1, 2, 1, 1, 1, 27, 25),
(342, '22-0002438', 1, 2, 1, 1, 1, 27, 25),
(343, '23-0003062', 1, 2, 1, 1, 1, 27, 25),
(344, '22-0002686', 1, 2, 1, 1, 1, 27, 25),
(345, '22-0001559', 1, 2, 1, 1, 1, 27, 25),
(346, '23-0003053', 1, 2, 1, 1, 1, 27, 25),
(347, '23-0003087', 1, 2, 1, 1, 1, 27, 25),
(348, '24-0003174', 1, 2, 1, 1, 1, 27, 25),
(349, '23-0003167', 1, 2, 1, 1, 1, 27, 25),
(350, '23-0003005', 1, 2, 1, 1, 1, 27, 25),
(351, '23-0003103', 1, 2, 1, 1, 1, 27, 25),
(352, '23-0003023', 1, 2, 1, 1, 1, 27, 25),
(353, '23-0003108', 1, 2, 1, 1, 1, 27, 25),
(354, '22-0002534', 1, 2, 1, 1, 1, 27, 25),
(355, '23-0003028', 1, 2, 1, 1, 1, 27, 25),
(356, '23-0003098', 1, 2, 1, 1, 1, 27, 25),
(357, '22-0002360', 1, 2, 1, 1, 1, 27, 25),
(358, '23-0003012', 1, 2, 1, 1, 1, 27, 25),
(359, '23-0003034', 1, 2, 1, 1, 1, 27, 25),
(360, '23-0003026', 1, 2, 1, 1, 1, 27, 25),
(361, '23-0003060', 1, 2, 1, 1, 1, 28, 26),
(362, '22-0003082', 1, 2, 1, 1, 1, 28, 26),
(363, '22-0002676', 1, 2, 1, 1, 1, 28, 26),
(364, '23-0003054', 1, 2, 1, 1, 1, 28, 26),
(365, '23-0003063', 1, 2, 1, 1, 1, 28, 26),
(366, '23-0003011', 1, 2, 1, 1, 1, 28, 26),
(367, '23-0003022', 1, 2, 1, 1, 1, 28, 26),
(368, '22-0002438', 1, 2, 1, 1, 1, 28, 26),
(369, '23-0003062', 1, 2, 1, 1, 1, 28, 26),
(370, '22-0002686', 1, 2, 1, 1, 1, 28, 26),
(371, '22-0001559', 1, 2, 1, 1, 1, 28, 26),
(372, '23-0003053', 1, 2, 1, 1, 1, 28, 26),
(373, '23-0003087', 1, 2, 1, 1, 1, 28, 26),
(374, '24-0003174', 1, 2, 1, 1, 1, 28, 26),
(375, '23-0003167', 1, 2, 1, 1, 1, 28, 26),
(376, '23-0003005', 1, 2, 1, 1, 1, 28, 26),
(377, '23-0003103', 1, 2, 1, 1, 1, 28, 26),
(378, '23-0003023', 1, 2, 1, 1, 1, 28, 26),
(379, '23-0003108', 1, 2, 1, 1, 1, 28, 26),
(380, '22-0002534', 1, 2, 1, 1, 1, 28, 26),
(381, '23-0003028', 1, 2, 1, 1, 1, 28, 26),
(382, '23-0003098', 1, 2, 1, 1, 1, 28, 26),
(383, '22-0002360', 1, 2, 1, 1, 1, 28, 26),
(384, '23-0003012', 1, 2, 1, 1, 1, 28, 26),
(385, '23-0003034', 1, 2, 1, 1, 1, 28, 26),
(386, '23-0003026', 1, 2, 1, 1, 1, 28, 26);

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('Present','Late','Absent') NOT NULL,
  `admission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `attendance_date`, `schedule_id`, `time_in`, `time_out`, `status`, `admission_id`) VALUES
(10, '2025-11-28', 27, '15:30:45', NULL, 'Absent', 314),
(11, '2025-11-28', 27, '15:34:46', NULL, 'Absent', 311);

-- --------------------------------------------------------

--
-- Table structure for table `course`
--

CREATE TABLE `course` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(50) NOT NULL,
  `course_name` varchar(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course`
--

INSERT INTO `course` (`course_id`, `course_code`, `course_name`) VALUES
(1, 'BSIS', 'Bachelor of Science in Information System'),
(2, 'BSAIS', 'Bachelor of Science in Accounting Information System'),
(3, 'BSED', 'Bachelor of Science in Secondary Education'),
(4, 'BEED', 'Bachelor of Science in Elementary Education');

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `lastname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Dean','Faculty','Ssc','MIS Admin') NOT NULL DEFAULT 'Faculty',
  `profile_pic` varchar(255) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`employee_id`, `firstname`, `lastname`, `email`, `password`, `role`, `profile_pic`, `barcode`) VALUES
(3, 'System', 'Admin', 'admin@example.com', '$2a$12$ClvHCgzmNUqVcY8yU3kRFuTtGhVaa/DzwPotNforXpD0O9vuRsXfS', 'MIS Admin', NULL, '3'),
(130256, 'Joshua', 'Tiongco', 'joshuawork@gmail.com', '$2y$10$cEO65fQYqSY9FLqOpHOXQ.gEDF5Av3pxtF1M7acBnH0V.ym2Fd8vS', 'Faculty', 'teacher_6927c9114cb762.74687646.jpg', '130256'),
(130257, 'Anthony', 'Rivera', 'antmanrivera@gmail.com', '$2y$10$H10siKbHc7JrKGL8wuBFB.R7981GefvPIjfTo1Q.nWK3yLbiOWxIa', 'Faculty', 'teacher_6927c810817e87.51309694.jpg', '130257'),
(130258, 'Erickson', 'Salunga', 'ems@gmail.com', '$2y$10$13OnUQam1LurfetrK.KMO.uiFzpQ7ZlzxcvszIH2DedthREDncjTG', 'Faculty', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `facility`
--

CREATE TABLE `facility` (
  `lab_id` int(11) NOT NULL,
  `lab_name` varchar(100) NOT NULL,
  `location` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `facility`
--

INSERT INTO `facility` (`lab_id`, `lab_name`, `location`) VALUES
(1, 'Computer Laboratory A', NULL),
(2, 'Computer Laboratory B', NULL),
(3, 'Computer Laboratory C', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `pc_assignment`
--

CREATE TABLE `pc_assignment` (
  `pc_assignment_id` int(11) NOT NULL,
  `student_id` varchar(10) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `pc_number` varchar(20) NOT NULL,
  `date_assigned` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pc_assignment`
--

INSERT INTO `pc_assignment` (`pc_assignment_id`, `student_id`, `lab_id`, `pc_number`, `date_assigned`) VALUES
(2, '22-0002403', 1, '3', '2025-11-27 00:00:00'),
(3, '22-0002148', 1, '15', '2025-11-27 00:00:00'),
(4, '22-0002148', 2, '12', '2025-11-27 00:00:00'),
(5, '22-0002403', 2, '1', '2025-11-27 00:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `schedule`
--

CREATE TABLE `schedule` (
  `schedule_id` int(11) NOT NULL,
  `lab_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `schedule_days` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedule`
--

INSERT INTO `schedule` (`schedule_id`, `lab_id`, `subject_id`, `employee_id`, `time_start`, `time_end`, `schedule_days`) VALUES
(7, 1, 9, 130257, '09:00:00', '12:00:00', NULL),
(8, 1, 10, 130257, '13:00:00', '15:00:00', NULL),
(9, 1, 11, 130257, '09:00:00', '12:00:00', NULL),
(10, 1, 12, 130257, '13:00:00', '15:00:00', NULL),
(11, 1, 13, 130257, '09:00:00', '12:00:00', NULL),
(12, 1, 14, 130257, '13:00:00', '15:00:00', NULL),
(13, 1, 15, 130257, '09:00:00', '12:00:00', NULL),
(14, 1, 16, 130257, '13:00:00', '15:00:00', NULL),
(15, 1, 17, 130258, '09:00:00', '12:00:00', NULL),
(16, 2, 18, 130256, '09:00:00', '12:00:00', 'Mon'),
(17, 2, 19, 130256, '13:00:00', '15:00:00', 'Mon'),
(18, 1, 20, 130256, '09:00:00', '12:00:00', 'Tue'),
(19, 2, 21, 130256, '13:00:00', '15:00:00', 'Tue'),
(20, 2, 22, 130256, '09:00:00', '12:00:00', 'Wed'),
(21, 2, 23, 130256, '13:00:00', '15:00:00', 'Wed'),
(22, 2, 24, 130256, '15:00:00', '18:00:00', 'Wed'),
(23, 2, 25, 130256, '09:00:00', '12:00:00', 'Thu'),
(24, 2, 26, 130256, '13:00:00', '15:00:00', 'Thu'),
(25, 2, 27, 130256, '09:00:00', '12:00:00', 'Fri'),
(26, 2, 28, 130256, '13:00:00', '15:00:00', 'Fri'),
(27, 2, 29, 130256, '15:00:00', '17:00:00', 'Fri');

-- --------------------------------------------------------

--
-- Table structure for table `section`
--

CREATE TABLE `section` (
  `section_id` int(11) NOT NULL,
  `section_name` varchar(50) NOT NULL,
  `level` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `section`
--

INSERT INTO `section` (`section_id`, `section_name`, `level`) VALUES
(1, '1A', 1),
(2, '2B', 1),
(3, '2A', 2),
(4, '2B', 2),
(5, '3A', 3),
(6, '3B', 3),
(7, '4A', 4),
(8, '4B', 4);

-- --------------------------------------------------------

--
-- Table structure for table `semester`
--

CREATE TABLE `semester` (
  `semester_id` int(11) NOT NULL,
  `ay_id` int(11) NOT NULL,
  `semester_now` enum('1','2') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semester`
--

INSERT INTO `semester` (`semester_id`, `ay_id`, `semester_now`) VALUES
(1, 1, '1'),
(2, 1, '2');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` varchar(10) NOT NULL,
  `rfid_number` varchar(50) NOT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT '',
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(50) DEFAULT '',
  `gender` varchar(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `rfid_number`, `profile_picture`, `first_name`, `middle_name`, `last_name`, `suffix`, `gender`) VALUES
(' 24-000342', ' 24-0003425', '', ' RUFFA', 'ROMERO', 'CALILUNG', '', 'Female'),
('19-0000124', '19-0000124', '', ' ALDRIN', 'PEREZ', 'FAVOR', '', 'Male'),
('20-0000651', '20-0000651', '', ' OLIVER', 'LANSANGAN', 'DELFIN', '', 'Male'),
('21-0000840', '21-0000840', '', ' DIETHER JOSHUA', 'SAGUN', 'CALAGUAS', '', 'Male'),
('21-0000897', '21-0000897', '', ' ERIS', 'ESPIRITU', 'PONIO', '', 'Male'),
('21-0000905', '21-0000905', '', ' CRISTOPHER JAMES', 'BARNES', 'ANGELES', '', 'Male'),
('21-0001062', '21-0001062', '', ' JOHN MICHAEL', 'FLORES', 'DIZON', '', 'Male'),
('21-0001280', '21-0001280', '', ' VINCE NICOLAS', 'ENRIQUEZ', 'SANGALANG', '', 'Male'),
('22-0001230', '22-0001230', '', ' PRINCE', 'JAN', 'VITUG', '', 'Male'),
('22-0001234', '22-0001234', '', ' ROSA', 'CAMMILE', 'MANGAYA', '', 'Female'),
('22-0001235', '22-0001235', '', ' JAYANNE', '', 'MONTEMAYOR', '', 'Female'),
('22-0001236', '22-0001236', '', ' DEANA', '', 'NULUD', '', 'Female'),
('22-0001237', '22-0001237', '', ' TENCHI', '', 'SENYO', '', 'Male'),
('22-0001238', '22-0001238', '', ' ALAN', '', 'TOLENTINO', '', 'Male'),
('22-0001239', '22-0001239', '', ' ANGELA', '', 'VALDEZ', '', 'Female'),
('22-0001456', '2874198611', '', 'LORENZO EMMANUEL', '', 'URBANO', '', 'Male'),
('22-0001559', '2874203987', '', 'NINO ANJELO', '', 'DIZON', '', 'Male'),
('22-0002120', '22-0002120', '', ' PATRICK JOHN', 'LAPIRA', 'ALIPIO', '', 'Male'),
('22-0002123', '22-0002123', '', ' JEROME ANGELO', 'LEJARDE', 'LANSANG', '', 'Male'),
('22-0002127', '22-0002127', '', ' NICOLE', '', 'ENRIQUEZ', '', 'Female'),
('22-0002128', '22-0002128', '', ' ANGELA', 'ENRIQUEZ', 'AVILA', '', 'Female'),
('22-0002129', '22-0002129', '', ' JOHN LESTER', 'GARCIA', 'BACANI', '', 'Male'),
('22-0002131', '22-0002131', '', ' JOHN CARL', 'DELA PENA', 'DIZON', '', 'Male'),
('22-0002141', '22-0002141', '', ' PRINCESS', 'OCAMPO', 'CALMA', '', 'Female'),
('22-0002142', '22-0002142', '', ' KYLE', 'MANALAC', 'FERNANDEZ', '', 'Female'),
('22-0002145', '22-0002145', '', ' AIRA', 'MANALAC', 'FERNANDEZ', '', 'Female'),
('22-0002146', '22-0002146', '', ' RIMARCH', 'ROQUE', 'DIZON', '', 'Male'),
('22-0002147', '22-0002147', '', ' LHOURD ANDREI', 'LEANO', 'GANZON', '', 'Male'),
('22-0002148', '2874103379', 'student_692499c35016b3.27346113.jpg', 'MARK GLEN', '', 'GUEVARRA', '', 'Male'),
('22-0002149', '22-0002149', '', ' JEROME', 'PAMAGAN', 'GARCIA', '', 'Male'),
('22-0002152', '22-0002152', '', ' MICAELLA', 'PINEDA', 'MILLOS', '', 'Female'),
('22-0002153', '22-0002153', '', ' ELAINE', 'SALALILA', 'MONTEMAYOR', '', 'Female'),
('22-0002154', '22-0002154', '', ' CLARENCE', 'BUAN', 'DULA', '', 'Male'),
('22-0002155', '22-0002155', '', ' ROY', 'DELA CRUZ', 'JUNTILLA', '', 'Male'),
('22-0002156', '22-0002156', '', ' ASHLIE JOHN', 'VALENCIA', 'GATCHALIAN', '', 'Male'),
('22-0002157', '22-0002157', '', ' RAINIER', 'JOVELLAR', 'LAXAMANA', '', 'Male'),
('22-0002158', '22-0002158', '', ' ROMAN', 'SANTOS', 'MERCADO', '', 'Male'),
('22-0002167', '22-0002167', '', ' GENER JR.', 'VALENCIA', 'MANLAPAZ', '', 'Male'),
('22-0002170', '22-0002170', '', ' LAWRENCE ANDREI', '', 'GUIAO', '', 'Male'),
('22-0002171', '22-0002171', '', ' LLANYELL', 'REYES', 'MANALANG', '', 'Male'),
('22-0002191', '22-0002191', '', ' JOHN EMIL', 'MANALAC', 'TUPAS', '', 'Male'),
('22-0002199', '22-0002199', '', ' JANIRO', 'MENDOZA', 'SERRANO', '', 'Male'),
('22-0002200', '22-0002200', '', ' MARK ANTHONY', 'SISON', 'VILLAFUERTE', '', 'Male'),
('22-0002201', '22-0002201', '', ' FRENCER GIL', 'MANANSALA', 'ROMERO', '', 'Male'),
('22-0002202', '22-0002202', '', ' LIMUEL', 'VARQUEZ', 'MIRANDA', '', 'Male'),
('22-0002204', '22-0002204', '', ' JONNARIE', 'MERCADO', 'ROLL', '', 'Female'),
('22-0002209', '22-0002209', '', ' RONIEL MARCO', 'PUNZALAN', 'BAYAUA', '', 'Male'),
('22-0002224', '22-0002224', '', ' JANESSA', 'HICBAN', 'SANTOS', '', 'Female'),
('22-0002225', '22-0002225', '', ' MARK EDRIAN', 'DE DIOS', 'ROQUE', '', 'Male'),
('22-0002226', '22-0002226', '', ' RALPH', 'AGUILAR', 'SIMBUL', '', 'Male'),
('22-0002264', '22-0002264', '', ' MICHELLE', 'DAGOY', 'GUANLAO', '', 'Female'),
('22-0002294', '22-0002294', '', ' JEROME', 'DETERA', 'OCAMPO', '', 'Male'),
('22-0002360', '2874090579', NULL, 'ERWIN', '', 'OCAMPO', '', 'Male'),
('22-0002365', '22-0002365', '', ' JOHN ARLEY', 'MANALANSAN', 'DABU', '', 'Male'),
('22-0002372', '22-0002372', '', ' TRICIA ANN', 'MANABAT', 'NEPOMUCENO', '', 'Female'),
('22-0002376', '22-0002376', '', ' CRISTINE', '', 'MAAMBONG', '', 'Male'),
('22-0002382', '22-0002382', '', ' VINCENT', '', 'TIATCO', '', 'Male'),
('22-0002387', '22-0002387', '', ' GUEN CARLO', '', 'GOMEZ', '', 'Male'),
('22-0002388', '22-0002388', '', ' JOSEPH LORENZ', 'DIMACALI', 'SISON', '', 'Male'),
('22-0002389', '22-0002389', '', ' JESSA', 'VERZOSA', 'GUANLAO', '', 'Female'),
('22-0002390', '22-0002390', '', ' NEIL TRISTAN', 'PAYUMO', 'MANGILIMAN', '', 'Male'),
('22-0002391', '0200721747', '', 'KHIAN CARL', '', 'HERODICO', '', 'Male'),
('22-0002393', '22-0002393', '', ' RAMLEY JON', 'RAMOS', 'MAGPAYO', '', 'Male'),
('22-0002394', '22-0002394', '', ' LEONEL', 'PACHICO', 'POPATCO', '', 'Male'),
('22-0002398', '22-0002398', '', ' KING WESHLEY', 'GALANG', 'MUTUC', '', 'Male'),
('22-0002400', '22-0002400', '', ' STEVEN', 'LOBERO', 'GONZALES', '', 'Male'),
('22-0002401', '22-0002401', '', ' RICHARD', 'BUNQUE', 'GUEVARRA', '', 'Male'),
('22-0002403', '2874096467', 'student_69249855880b78.45051581.jpg', 'CHRISTOPHER', '', 'PANOY', '', 'Male'),
('22-0002407', '22-0002407', '', ' JHAY-R', 'LLENAS', 'MERCADO', '', 'Male'),
('22-0002409', '22-0002409', '', ' VAL NERIE', 'ONG', 'ESPELETA', '', 'Male'),
('22-0002413', '22-0002413', '', ' JOHN LOUISE', 'CUNANAN', 'SEMSEM', '', 'Male'),
('22-0002414', '22-0002414', '', ' RAPH JUSTINE', 'BAUTISTA', 'BUTIAL', '', 'Male'),
('22-0002415', '22-0002415', '', ' ANGEL ROSE ANNE', 'FABROA', 'MALLARI', '', 'Female'),
('22-0002416', '22-0002416', '', ' KELSEY KEMP', 'SAZON', 'BONOAN', '', 'Male'),
('22-0002419', '22-0002419', '', ' PRINCESS SHAINE', 'BUCUD', 'SANTIAGO', '', 'Female'),
('22-0002420', '22-0002420', '', ' YVES ANDREI', 'MANALO', 'SANTOS', '', 'Male'),
('22-0002421', '22-0002421', '', ' CHRISTINE ANNE', 'MALLARI', 'FLORENDO', '', 'Female'),
('22-0002425', '22-0002425', '', ' RICHMOND', 'MARTIN', 'SAFICO', '', 'Male'),
('22-0002431', '22-0002431', '', ' JANRIX HARVEY', 'CRUZ', 'RIVERA', '', 'Male'),
('22-0002434', '22-0002434', '', ' AERIAL JERAMY', 'APARICI', 'LAYUG', '', 'Male'),
('22-0002436', '22-0002436', '', ' RUSSEL KENNETH', 'CASTLLO', 'LIM', '', 'Male'),
('22-0002438', '2874285907', '', 'ANGELITO', '', 'CRUZ', '', 'Male'),
('22-0002439', '22-0002439', '', ' JOANNA', 'DUNGCA', 'JULIAN', '', 'Female'),
('22-0002442', '22-0002442', '', ' PRINCE ALVIER', 'GALANG', 'NUNEZ', '', 'Male'),
('22-0002453', '22-0002453', '', ' DEXTER', 'SALALILA', 'VILLEGAS', '', 'Male'),
('22-0002455', '22-0002455', '', ' JHAYZHELLE', 'DUNGCA', 'ALVARADO', '', 'Male'),
('22-0002458', '22-0002458', '', ' VERONICA', 'ALBISA', 'MERCADO', '', 'Female'),
('22-0002460', '22-0002460', '', ' JOHN MICHAEL', 'JIMENEZ', 'ELILIO', '', 'Male'),
('22-0002467', '22-0002467', '', ' ROSE ANN', 'DELA CRUZ', 'DELA ROSA', '', 'Female'),
('22-0002507', '22-0002507', '', ' ABRAHAM CHRISTIAN', 'SIMBAHAN', 'GAPPI', '', 'Male'),
('22-0002509', '22-0002509', '', ' JHON LOUIE', 'BOGNOT', 'DIZON', '', 'Male'),
('22-0002525', '22-0002525', '', ' JOHN REVELYN', 'DURAN', 'GONZALES', '', 'Male'),
('22-0002534', '2874286419', '', 'RHAINE JUSTIN', '', 'MANALAC', '', 'Male'),
('22-0002676', '2874202451', NULL, 'QUEEN MEILANIE', '', 'BERNIL', '', 'Female'),
('22-0002686', '2874286163', '', 'JOHN BENEDICT', '', 'DEL ROSARIO', '', 'Male'),
('22-0002726', '22-0002726', '', ' QUEEN MEILANIE', 'BILLENA', 'BENRIL', '', 'Female'),
('22-0002822', '2874006355', '', 'RHEALLE', '', 'ALKUINO', '', 'Female'),
('22-0003054', '2874092627', NULL, 'KATE LYN', '', 'BUAN', '', 'Female'),
('22-0003082', '2874198355', '', 'JAYSON', '', 'BACSAN', '', 'Male'),
('23-0002973', '23-0002973', '', ' JOHN MICHAEL', 'GALANG', 'DAVID', '', 'Male'),
('23-0003005', '2874288467', '', 'MERWIN', '', 'HIPOLITO', '', 'Male'),
('23-0003011', '2874201939', '', 'IGIDIAN VINCE', '', 'CASTRO', '', 'Male'),
('23-0003012', '2874090835', '', 'REYMART', '', 'PINEDA', '', 'Male'),
('23-0003021', '23-0003021', '', ' C-JAY', 'HICBAN', 'SANTOS', '', 'Male'),
('23-0003022', '2874288723', '', 'RENZ YUAN', '', 'CAYANAN', '', 'Male'),
('23-0003023', '2874204243', '', 'LEAN', '', 'LAXAMANA', '', 'Female'),
('23-0003026', '2874091091', '', 'JULIUS CEDRICK', '', 'VIRAY', '', 'Male'),
('23-0003028', '2874287955', '', 'MARK ATHAN', '', 'MANALANG', '', 'Male'),
('23-0003031', '23-0003031', '', ' JHON MICHAEL', 'OCAMPO', 'BATAC', '', 'Male'),
('23-0003034', '23-0003034', '', ' JOSEPH MIGUEL', '', 'URBANO', '', 'Male'),
('23-0003053', '2874093139', '', 'ROY FRANCIS', '', 'ENRIQUEZ', '', 'Male'),
('23-0003054', '23-0003054', '', ' KATE LYN', 'PINEDA', 'BUAN', '', 'Female'),
('23-0003058', '23-0003058', '', ' KEN HARVEY', 'REQUIRON', 'SORIANO', '', 'Male'),
('23-0003060', '2874284627', '', 'JOHN KEISLY', '', 'BACANI', '', 'Male'),
('23-0003062', '2874202195', '', 'JOHN CLARENCE', '', 'DAVID', '', 'Male'),
('23-0003063', '2874092371', '', 'TIMOTHY EARL', '', 'BUAN', '', 'Male'),
('23-0003082', '23-0003082', '', ' JAYSON', 'INDIONGCO', 'BACSAN', '', 'Male'),
('23-0003087', '2874290259', '', 'MHARK CHEDRICK', '', 'FERNANDO', '', 'Male'),
('23-0003098', '23-0003098', '', ' NICK IVAN', 'BUAN', 'MARIANO', '', 'Male'),
('23-0003103', '2874202707', '', 'JULIANA CLAIR', '', 'IGNACIO', '', 'Female'),
('23-0003108', '2874290003', '', 'RENELLE ROBIE', '', 'LOPEZ', '', 'Male'),
('23-0003167', '2643794180', '', 'RYAN', '', 'GUINTO', '', 'Male'),
('24 -000326', '0200729939', '', 'GIRLLY', '', 'FERNANDEZ', '', 'Female'),
('24-0003044', '24-0003044', '', ' ELKAN', 'ALONZO', 'SARMIENTO', '', 'Male'),
('24-0003174', '2874284371', '', 'REX', '', 'GATCHALIAN', '', 'Male'),
('24-0003256', '0200722259', '', 'JUSTINE', '', 'ANGELES', '', 'Male'),
('24-0003261', '2891602259', '', 'JOHN PAUL', '', 'ARCILLA', '', 'Male'),
('24-0003262', '2874008915', '', 'KAREN', '', 'MONTES', '', 'Female'),
('24-0003267', '24-0003267', '', ' KIM WESLEY', 'ANTONIO', 'PERALTA', '', 'Male'),
('24-0003280', '2874006867', '', 'EDRON', '', 'GARCIA', '', 'Male'),
('24-0003285', '24-0003285', '', ' JUSTINE', 'PITUC', 'SINGAN', '', 'Male'),
('24-0003290', '2874004563', '', 'RONNIE JR.', '', 'HALOG', '', 'Male'),
('24-0003292', '2874000723', '', 'SHIANN KELLY', '', 'PAYUMO', '', 'Male'),
('24-0003303', '2874008659', '', 'ANTONETTE', '', 'BERNARDO', '', 'Female'),
('24-0003306', '2874002259', '', 'JOHN BENEDICT', '', 'PERRERAS', '', 'Male'),
('24-0003307', '2874002515', '', 'JERALD', '', 'GALANG', '', 'Male'),
('24-0003308', '2874002771', '', 'WARREN KING', '', 'CANLAS', '', 'Male'),
('24-0003309', '24-0003309', '', ' IYA NEL', 'SERRANO', 'MANGARING', '', 'Female'),
('24-0003310', '24-0003310', '', ' SHIN', 'GARCIA', 'BARTOCILLO', '', 'Male'),
('24-0003314', '2874094675', '', 'JHON FRANCIS', '', 'ALAVE', '', 'Male'),
('24-0003315', '0200724051', '', 'ALEXANDER JEHRIEL', '', 'NULUD', '', 'Male'),
('24-0003318', '2874007379', '', 'NICOLE', '', 'SAMBILE', '', 'Female'),
('24-0003321', '0200721491', '', 'MARVIN JOEY', '', 'APAREJADO', '', 'Male'),
('24-0003325', '24-0003325', '', ' MARLYN', '', 'MERCADO', '', 'Female'),
('24-0003331', '0200730195', '', 'SOPIA MAE', '', 'GUINTO', '', 'Female'),
('24-0003339', '24-0003339', '', ' KEVIN', 'MARIANO', 'CASTRO', '', 'Male'),
('24-0003343', '2874290515', '', 'ARJAY', '', 'DEL CASTILLO', '', 'Male'),
('24-0003349', '2874001491', '', 'JAZELLE ANNE', '', 'BATAS', '', 'Female'),
('24-0003362', '24-0003362', '', ' ERIC', 'SUYOM', 'CADOCOY', '', 'Male'),
('24-0003375', '24-0003375', '', ' KATHEINE JOY', 'CORTEZ', 'FERNANDO', '', 'Female'),
('24-0003393', '2874007891', '', 'ERICAH MAE', '', 'VALENCIA', '', 'Female'),
('24-0003410', '0200729683', '', 'JESSICA', '', 'SALALILA', '', 'Female'),
('24-0003414', '2874007123', '', 'VHON LEAMBEER', '', 'GONZALES', '', 'Male'),
('24-0003425', '2874005075', '', 'RUFFA', '', 'CALILUNG', '', 'Female'),
('24-0003426', '2874001747', '', 'JOHN PAUL', '', 'SANTOS', '', 'Male'),
('24-0003433', '24-0003433', '', ' LYKA NICOLE', 'TORRES', 'LAYUG', '', 'Female'),
('24-0003434', '24-0003434', '', ' TRISTAN', 'LUSUNG', 'DUQUE', '', 'Male'),
('24-0003435', '2874001235', '', 'ALEXA KEITH', '', 'BOSTERO', '', 'Female'),
('25-0003447', '2874000467', NULL, 'LEA', '', 'SAMSON', '', 'Male'),
('25-0003688', '25-0003688', '', ' TRISHA', 'CABILES', 'BARRUGA', '', 'Female'),
('25-0003690', '25-0003690', '', ' KERWIN', 'PADILLA', 'BUAN', '', 'Male'),
('25-0003691', '25-0003691', '', ' JOSHUA', 'RAMIREZ', 'CAMITAN', '', 'Male'),
('25-0003692', '25-0003692', '', ' JOHN CHLOE', 'TUMINTIN', 'CASUPANAN', '', 'Male'),
('25-0003693', '25-0003693', '', ' DAVE GABRIEL', 'BALTAZAR', 'CRUZ', '', 'Male'),
('25-0003694', '25-0003694', '', ' KAYCEE LYN', 'NARVAREZ', 'DIMAL', '', 'Female'),
('25-0003695', '25-0003695', '', ' NORMAN', 'SAMPANG', 'FRESNOZA JR.', '', 'Male'),
('25-0003698', '25-0003698', '', ' MAUI', 'MALLARI', 'MARCELO', '', 'Female'),
('25-0003704', '25-0003704', '', ' ELLAIZA', 'BACANI', 'NEPOMUCENO', '', 'Female'),
('25-0003706', '25-0003706', '', ' JEROME', 'TORANO', 'PINEDA', '', 'Male'),
('25-0003707', '25-0003707', '', ' JOSHUA', 'LUCINO', 'PINEDA', '', 'Male'),
('25-0003708', '2874015315', '', ' JOLAINE', 'JIMENEZ', 'ANDAMON', '', 'Female'),
('25-0003709', '25-0003709', '', ' EMY JANE', 'LUBIANO', 'ROYO', '', 'Female'),
('25-0003711', '25-0003711', '', ' CID', 'MALIGLIG', 'SOTTO', '', 'Male'),
('25-0003736', '25-0003736', '', ' CINDY', 'ENRIQUEZ', 'ROQUE', '', 'Female'),
('25-0003751', '25-0003751', '', ' GERALD', 'DELA CRUZ', 'PANTIG', '', 'Male'),
('25-0003756', '25-0003756', '', ' TRISTAN', 'CENAL', 'BUAN', '', 'Male'),
('25-0003763', '25-0003763', '', ' JOHN RUSTI', 'BUTIAL', 'NIO', '', 'Male'),
('25-0003765', '2874092883', '', 'IVAN', '', 'MARIANO', '', 'Male'),
('25-0003768', '25-0003768', '', ' KIRK RINGO', 'BEJASA', 'SERIOS', '', 'Male'),
('25-0003771', '25-0003771', '', ' JAN MARK', 'PAMINTUAN', 'TUAZON', '', 'Male'),
('25-0003774', '25-0003774', '', ' KYLE ZEDDRICK', 'MACALINO', 'SUBOC', '', 'Male'),
('25-0003781', '0200724307', '', 'SHANNEN', '', 'MONSALUD', '', 'Female'),
('25-0003782', '25-0003782', '', ' ANGEL', 'LOBERO', 'GONZALES', '', 'Female'),
('26-4378547', '26-43785476', '', ' JHOANA MARIE', 'MANLULU', 'SALVADOR', '', 'Female'),
('2643794436', '2643794436', '', ' Mark', 'Glen', 'Pineda', '', 'Male'),
('student_id', '	rfid_number', 'profile_picture', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender');

-- --------------------------------------------------------

--
-- Table structure for table `subject`
--

CREATE TABLE `subject` (
  `subject_id` int(11) NOT NULL,
  `subject_code` varchar(50) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `units` int(11) NOT NULL,
  `lecture` int(11) NOT NULL,
  `laboratory` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subject`
--

INSERT INTO `subject` (`subject_id`, `subject_code`, `subject_name`, `units`, `lecture`, `laboratory`) VALUES
(9, 'CC101-LAB', 'Intro To Computing', 3, 0, 3),
(10, 'CC102-LEC', 'Computer Programming', 2, 2, 0),
(11, 'CC102-LAB', 'Computer Programming', 3, 0, 3),
(12, 'CC101-LEC', 'Intro To Computing', 2, 2, 0),
(13, 'ISPE102-LAB', 'IS Innovation And New Technologies', 3, 0, 3),
(14, 'ISPE102-LEC', 'IS Innovation And New Technologies', 2, 2, 0),
(15, 'IS103', 'IT Infrastructure', 3, 0, 3),
(16, 'IS103-LEC', 'IT Infrastructure', 2, 2, 0),
(17, 'IS104', 'System Analysis And Design', 3, 0, 3),
(18, 'CC106-LAB', 'Application Development And Emerging Technologies', 3, 0, 3),
(19, 'CC106-LEC', 'Application Development And Emerging Technologies', 2, 2, 0),
(20, 'CC104-LAB', 'Data Structure And Algorithm', 3, 0, 3),
(21, 'CC104-LEC', 'Data Structure And Algorithm', 2, 2, 0),
(22, 'IS306-4A-LAB', 'Cloud Computing', 3, 0, 3),
(23, 'IS306-4A-LEC', 'Cloud Computing', 2, 2, 0),
(24, 'IS102', 'Free Electives', 3, 0, 3),
(25, 'IS306-4B-LAB', 'Cloud Computing', 3, 0, 3),
(26, 'IS306-4B-LEC', 'Cloud Computing', 2, 2, 0),
(27, 'IS101-LAB', 'Free Elective 1', 3, 0, 3),
(28, 'IS101-LEC', 'Free Elective 1', 2, 2, 0),
(29, 'IS102-LEC', 'Free Elective 2', 2, 2, 0);

-- --------------------------------------------------------

--
-- Table structure for table `year_level`
--

CREATE TABLE `year_level` (
  `year_id` int(11) NOT NULL,
  `year_name` varchar(20) NOT NULL,
  `level` tinyint(4) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `year_level`
--

INSERT INTO `year_level` (`year_id`, `year_name`, `level`) VALUES
(1, '1', 1),
(2, '2', 2),
(3, '3', 3),
(4, '4', 4);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_year`
--
ALTER TABLE `academic_year`
  ADD PRIMARY KEY (`ay_id`);

--
-- Indexes for table `admission`
--
ALTER TABLE `admission`
  ADD PRIMARY KEY (`admission_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `academic_year_id` (`academic_year_id`),
  ADD KEY `semester_id` (`semester_id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `year_level_id` (`year_level_id`),
  ADD KEY `course_id` (`course_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD KEY `schedule_id` (`schedule_id`),
  ADD KEY `admission_id` (`admission_id`);

--
-- Indexes for table `course`
--
ALTER TABLE `course`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_barcode` (`barcode`);

--
-- Indexes for table `facility`
--
ALTER TABLE `facility`
  ADD PRIMARY KEY (`lab_id`);

--
-- Indexes for table `pc_assignment`
--
ALTER TABLE `pc_assignment`
  ADD PRIMARY KEY (`pc_assignment_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `lab_id` (`lab_id`);

--
-- Indexes for table `schedule`
--
ALTER TABLE `schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `lab_id` (`lab_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `section`
--
ALTER TABLE `section`
  ADD PRIMARY KEY (`section_id`);

--
-- Indexes for table `semester`
--
ALTER TABLE `semester`
  ADD PRIMARY KEY (`semester_id`),
  ADD KEY `ay_id` (`ay_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `rfid_number` (`rfid_number`);

--
-- Indexes for table `subject`
--
ALTER TABLE `subject`
  ADD PRIMARY KEY (`subject_id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`);

--
-- Indexes for table `year_level`
--
ALTER TABLE `year_level`
  ADD PRIMARY KEY (`year_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_year`
--
ALTER TABLE `academic_year`
  MODIFY `ay_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `admission`
--
ALTER TABLE `admission`
  MODIFY `admission_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=387;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130259;

--
-- AUTO_INCREMENT for table `facility`
--
ALTER TABLE `facility`
  MODIFY `lab_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pc_assignment`
--
ALTER TABLE `pc_assignment`
  MODIFY `pc_assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `schedule`
--
ALTER TABLE `schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `section`
--
ALTER TABLE `section`
  MODIFY `section_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `semester`
--
ALTER TABLE `semester`
  MODIFY `semester_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subject`
--
ALTER TABLE `subject`
  MODIFY `subject_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `year_level`
--
ALTER TABLE `year_level`
  MODIFY `year_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admission`
--
ALTER TABLE `admission`
  ADD CONSTRAINT `admission_academic_year_fk` FOREIGN KEY (`academic_year_id`) REFERENCES `academic_year` (`ay_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_course_fk` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_schedule_fk` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_section_fk` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_semester_fk` FOREIGN KEY (`semester_id`) REFERENCES `semester` (`semester_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_subject_fk` FOREIGN KEY (`subject_id`) REFERENCES `subject` (`subject_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `admission_year_level_fk` FOREIGN KEY (`year_level_id`) REFERENCES `year_level` (`year_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_admission_fk` FOREIGN KEY (`admission_id`) REFERENCES `admission` (`admission_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `attendance_schedule_fk` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `pc_assignment`
--
ALTER TABLE `pc_assignment`
  ADD CONSTRAINT `pc_assignment_lab_fk` FOREIGN KEY (`lab_id`) REFERENCES `facility` (`lab_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `pc_assignment_student_fk` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `schedule`
--
ALTER TABLE `schedule`
  ADD CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `facility` (`lab_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `schedule_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subject` (`subject_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `schedule_ibfk_3` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `semester`
--
ALTER TABLE `semester`
  ADD CONSTRAINT `semester_ibfk_1` FOREIGN KEY (`ay_id`) REFERENCES `academic_year` (`ay_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

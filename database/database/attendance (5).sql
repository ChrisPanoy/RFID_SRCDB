-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 29, 2025 at 01:13 PM
-- Server version: 8.0.37
-- PHP Version: 8.4.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `aiesccsc_attendance`
--

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int NOT NULL,
  `student_id` varchar(20) DEFAULT NULL,
  `scan_time` datetime DEFAULT NULL,
  `status` enum('Present','Late','Absent','Signed Out') DEFAULT NULL,
  `subject_id` int DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `scan_time`, `status`, `subject_id`, `subject_name`) VALUES
(983, '220002405', '2025-09-27 23:43:16', 'Present', 29, 'Cloud Computing'),
(985, '220002404', '2025-09-27 23:46:24', 'Present', 29, 'Cloud Computing'),
(987, '220002405', '2025-09-27 23:47:07', 'Signed Out', 29, 'Cloud Computing'),
(988, '220002376', '2025-09-27 23:52:08', 'Present', 29, 'Cloud Computing'),
(989, '220002404', '2025-09-27 23:52:16', 'Signed Out', 29, 'Cloud Computing'),
(990, '220002376', '2025-09-27 23:52:20', 'Signed Out', 29, 'Cloud Computing'),
(992, '220002405', '2025-09-28 00:41:57', 'Present', 30, 'Application Development And Emerging Technologies'),
(994, '220002405', '2025-09-28 00:43:47', 'Signed Out', 30, 'Application Development And Emerging Technologies'),
(995, '220002405', '2025-09-28 09:53:50', 'Present', 29, 'Cloud Computing'),
(996, '220002405', '2025-09-28 09:54:08', 'Signed Out', 29, 'Cloud Computing'),
(997, '220002405', '2025-09-28 09:54:20', 'Present', 29, 'Cloud Computing'),
(998, '220002405', '2025-09-28 09:55:02', 'Signed Out', 29, 'Cloud Computing'),
(999, '220002376', '2025-09-28 09:59:44', 'Present', 32, 'CAPSTONE 1'),
(1000, '220002376', '2025-09-28 10:03:37', 'Signed Out', 32, 'CAPSTONE 1'),
(1001, '220002405', '2025-09-28 10:05:42', 'Present', 31, 'FREE ELECTIVES'),
(1002, '220002405', '2025-09-28 10:12:10', 'Signed Out', 31, 'FREE ELECTIVES'),
(1003, '220002405', '2025-09-28 10:14:33', 'Present', 31, 'FREE ELECTIVES');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1004;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

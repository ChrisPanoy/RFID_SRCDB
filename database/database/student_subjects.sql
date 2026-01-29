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
-- Table structure for table `student_subjects`
--

CREATE TABLE `student_subjects` (
  `id` int NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `subject_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `student_subjects`
--

INSERT INTO `student_subjects` (`id`, `student_id`, `subject_id`, `created_at`) VALUES
(129, '220002376', 33, '2025-09-27 15:36:57'),
(131, '220002404', 33, '2025-09-27 15:36:57'),
(132, '220002405', 33, '2025-09-27 15:36:57'),
(133, '220002376', 32, '2025-09-27 15:37:06'),
(135, '220002404', 32, '2025-09-27 15:37:06'),
(136, '220002405', 32, '2025-09-27 15:37:06'),
(137, '220002376', 30, '2025-09-27 15:37:14'),
(139, '220002404', 30, '2025-09-27 15:37:14'),
(140, '220002405', 30, '2025-09-27 15:37:14'),
(141, '220002376', 29, '2025-09-27 15:38:11'),
(143, '220002404', 29, '2025-09-27 15:38:11'),
(144, '220002405', 29, '2025-09-27 15:38:11'),
(145, '220002376', 31, '2025-09-27 15:38:18'),
(147, '220002404', 31, '2025-09-27 15:38:18'),
(148, '220002405', 31, '2025-09-27 15:38:18'),
(149, '220002376', 35, '2025-09-27 15:41:12'),
(151, '220002404', 35, '2025-09-27 15:41:12'),
(152, '220002405', 35, '2025-09-27 15:41:12');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject` (`student_id`,`subject_id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `student_subjects`
--
ALTER TABLE `student_subjects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=153;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD CONSTRAINT `fk_student_subjects_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_student_subjects_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

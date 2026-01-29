-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 29, 2025 at 01:14 PM
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
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int NOT NULL,
  `subject_code` varchar(20) DEFAULT NULL,
  `subject_name` varchar(100) DEFAULT NULL,
  `teacher_id` int DEFAULT NULL,
  `schedule_days` varchar(50) DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `lab` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `subject_code`, `subject_name`, `teacher_id`, `schedule_days`, `start_time`, `end_time`, `lab`) VALUES
(29, 'IS306', 'Cloud Computing', 1, 'Sun', '09:50:00', '09:55:00', 'Computer Lab B'),
(30, 'CC106', 'Application Development And Emerging Technologies', 1, 'Sun', '00:00:00', '00:50:00', 'Computer Lab A'),
(31, 'ISE102', 'FREE ELECTIVES', 1, 'Sun', '10:00:00', '10:30:00', 'Computer Lab A'),
(32, '105', 'CAPSTONE 1', 1, 'Sun', '09:55:00', '10:00:00', 'Computer Lab A'),
(33, '104', 'CAPSTONE 2', 1, 'Sun', '10:01:00', '10:00:00', 'Computer Lab B'),
(34, 'IS103', 'I.T Infrastructure', 6, NULL, NULL, NULL, 'Computer Lab B'),
(35, 'ISPE102', 'IS INNOVATION AND NEW TECHNOLOGIES', 6, NULL, NULL, NULL, 'Computer Lab B');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

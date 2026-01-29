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
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int NOT NULL,
  `student_id` varchar(20) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `section` varchar(50) DEFAULT NULL,
  `course` varchar(50) DEFAULT NULL,
  `year_level` int DEFAULT NULL,
  `barcode` varchar(100) DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `pc_number` varchar(50) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_id`, `name`, `section`, `course`, `year_level`, `barcode`, `profile_pic`, `pc_number`, `gender`) VALUES
(16, '220002376', 'Maambong, Cristine', 'A', 'BSIS', 4, '220002376', 'student_68d21e10949875.68067130.jpg', '1', 'Female'),
(17, '220002404', 'Mercado, Roman', 'A', 'BSIS', 4, '220002404', 'student_68d21e4a79cfd2.44576242.jpg', '35', 'Male'),
(24, '220002405', 'Popatco, Leonel', 'A', 'BSIS', 4, '220002405', 'student_68d3abca04ca66.30142960.jfif', '1003', 'Male'),
(27, '220002403', 'Richmon Carabeo', 'A', 'BSIS', 4, '220002403', 'student_68d89924f303e7.01450361.jpg', '1223', 'Male');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

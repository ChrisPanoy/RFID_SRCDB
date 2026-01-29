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
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('admin','teacher','student') DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `name`, `verified`) VALUES
(1, NULL, 'christophermadeja7@gmail.com', '$2a$12$at4sBxtLEXnsoTMR.iOhgOJQlkfBCQab3hgirlU3vwiOyTJtFmFea', 'admin', 'Christopher', 1),
(2, NULL, 'joshuationgco@gmail.com', '$2y$10$R8O8iE5m8JGj0UAwYFDkg.ve8gVE0wE.PsqJd3EiLgNGsns6UbWca', 'teacher', 'Joshua Tiongco', 1),
(3, NULL, 'antmanrivera@gmail.com', '$2y$10$CEyzWls3IWqZwERllcUCVe7.dLmtab7kbxqrHD6yjmzQc8zjyarqq', 'teacher', 'Anthony Rivera', 1),
(4, NULL, 'ems@gmail.com', '$2y$10$.5xN2CsuUhTj9zIhAalrd.wZZ.1EX0crTohoQZYcee3iSn44ojdfW', 'teacher', 'Erickson Salunga', 1),
(5, NULL, 'fernan@gmail.com', '$2y$10$rmoaVSjXwhNsKMSBs5fIxO0d5/L0fqIU/BFnu3VyACfjsVWVqUB7K', 'teacher', 'Fernan Layug', 1),
(6, NULL, 'Roman@gmail.com', '$2y$10$FUJe8SRZhzr53.GBTgK/au6gNOP6kxRyH69CMNv8qMzRHHljbyYDy', 'teacher', 'Roman Mercado', 1),
(7, NULL, 'anthonyrivera@gmail.com', '$2y$10$WWScui2V1a3JFNxEV2wbQuqaISsH7GW19/7wcjskv4d5bGeqBQf9q', 'teacher', 'ANTHONY RIVERA', 1);

--
-- Indexes for dumped tables
--

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
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

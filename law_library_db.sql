-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 07, 2026 at 08:36 AM
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
-- Database: `law_library_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `books`
--

CREATE TABLE `books` (
  `id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `author` varchar(150) NOT NULL,
  `serial_no` varchar(100) NOT NULL,
  `total_copies` int(11) NOT NULL DEFAULT 1,
  `available_copies` int(11) NOT NULL DEFAULT 1,
  `is_available` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `books`
--

INSERT INTO `books` (`id`, `title`, `author`, `serial_no`, `total_copies`, `available_copies`, `is_available`) VALUES
(1, 'Philippine Constitution', 'Hector De Leon', 'LAW-0001', 1, 1, 1),
(2, 'Revised Penal Code', 'Luis Reyes', 'LAW-0002', 1, 1, 1),
(4, 'Civil Code of the Philippines', 'Arturo Tolentino', 'LAW-0003', 1, 1, 1),
(5, 'Labor Code of the Philippines', 'Cesario Azucena', 'LAW-0004', 1, 0, 0),
(6, 'Criminal Law Reviewer', 'Luis Reyes', 'LAW-0005', 1, 1, 1),
(11, 'ABC', 'ABC', '12345', 1, 1, 1),
(12, 'KMJS', 'Jesicca Soho', 'LAW-1357', 1, 1, 1),
(13, 'TV Patrol', 'Mike Enriquez', 'LAW-1358', 1, 1, 1),
(14, 'It\'s showtime', 'Abs Cbn', 'LAW-1313', 1, 1, 1),
(15, 'Triple A', 'Fred Monae', 'Book 678', 1, 1, 1),
(16, 'Liberty of Codes', 'Ash Poke', 'Book 679', 1, 1, 1),
(17, 'Book of life', 'AAA AVB', 'Book-12345', 1, 1, 1),
(18, 'Networking', 'Syrell Zamora', 'LAW-0006', 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `borrowing`
--

CREATE TABLE `borrowing` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `book_id` int(11) NOT NULL,
  `borrow_date` date NOT NULL,
  `borrow_days` tinyint(4) NOT NULL DEFAULT 1,
  `due_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `borrowing`
--

INSERT INTO `borrowing` (`id`, `student_id`, `book_id`, `borrow_date`, `borrow_days`, `due_date`, `return_date`) VALUES
(1, 2, 2, '2026-05-04', 1, NULL, '2026-05-04'),
(2, 1, 2, '2026-05-04', 1, NULL, '2026-05-04'),
(3, 2, 2, '2026-05-04', 1, NULL, '2026-05-04'),
(4, 2, 1, '2026-05-04', 1, NULL, '2026-05-04'),
(5, 2, 1, '2026-05-04', 1, NULL, '2026-05-04'),
(6, 2, 2, '2026-05-04', 1, NULL, '2026-05-04'),
(7, 1, 1, '2026-05-04', 1, NULL, '2026-05-04'),
(8, 1, 2, '2026-05-04', 1, NULL, '2026-05-04'),
(9, 1, 3, '2026-05-04', 1, NULL, '2026-05-04'),
(10, 1, 1, '2026-05-04', 1, NULL, '2026-05-04'),
(11, 1, 2, '2026-05-04', 1, NULL, '2026-05-04'),
(12, 1, 3, '2026-05-04', 1, NULL, '2026-05-04'),
(13, 1, 2, '2026-05-05', 1, NULL, '2026-05-05'),
(14, 1, 3, '2026-05-05', 1, NULL, '2026-05-07'),
(15, 1, 1, '2026-05-05', 1, NULL, '2026-05-07'),
(16, 5, 4, '2026-05-15', 1, NULL, '2026-05-16'),
(17, 5, 5, '2026-05-15', 1, NULL, '2026-05-15'),
(18, 3, 2, '2026-05-15', 1, NULL, '2026-05-15'),
(19, 3, 6, '2026-05-15', 1, NULL, '2026-05-15'),
(20, 1, 1, '2026-05-15', 1, NULL, '2026-05-29'),
(21, 1, 2, '2026-05-15', 1, NULL, '2026-05-16'),
(22, 1, 5, '2026-05-15', 1, NULL, '2026-05-16'),
(23, 8, 2, '2026-05-16', 1, NULL, '2026-07-17'),
(24, 8, 4, '2026-05-16', 1, NULL, '2026-07-17'),
(25, 8, 5, '2026-05-16', 1, NULL, '2026-07-17'),
(26, 2, 6, '2026-05-29', 1, NULL, '2026-07-17'),
(27, 2, 2, '2026-07-17', 1, NULL, '2026-07-17'),
(28, 2, 1, '2026-07-17', 1, NULL, '2026-07-17'),
(29, 2, 4, '2026-07-17', 1, NULL, '2026-07-17'),
(30, 5, 1, '2026-07-23', 1, NULL, '2026-07-23'),
(31, 5, 2, '2026-07-23', 1, NULL, '2026-07-23'),
(32, 5, 4, '2026-07-23', 1, NULL, '2026-07-23'),
(33, 5, 5, '2026-07-23', 1, NULL, '2026-07-23'),
(34, 1, 1, '2026-07-29', 1, NULL, '2026-08-07'),
(35, 1, 2, '2026-07-29', 1, NULL, '2026-07-29'),
(36, 1, 5, '2026-07-29', 1, NULL, '2026-07-29'),
(37, 3, 4, '2026-08-07', 1, NULL, '2026-08-07'),
(38, 5, 6, '2026-08-07', 1, NULL, '2026-08-07'),
(39, 3, 4, '2026-08-14', 1, NULL, '2026-08-14'),
(40, 3, 2, '2026-08-14', 1, NULL, '2026-08-29'),
(41, 3, 6, '2026-08-14', 1, NULL, '2026-08-29'),
(42, 14, 4, '2026-08-14', 1, NULL, '2026-08-29'),
(43, 14, 5, '2026-08-14', 1, NULL, '2026-08-14'),
(44, 6, 1, '2026-08-28', 1, NULL, '2026-08-28'),
(45, 1, 1, '2026-08-29', 2, '2026-08-31', '2026-08-29'),
(46, 1, 5, '2026-08-29', 2, '2026-08-31', '2026-08-29'),
(47, 13, 1, '2026-08-29', 2, '2026-08-31', '2026-09-25'),
(48, 13, 5, '2026-08-29', 2, '2026-08-31', NULL),
(49, 17, 2, '2026-09-25', 2, '2026-09-27', '2026-09-25'),
(50, 17, 17, '2026-09-25', 3, '2026-09-28', '2026-09-25'),
(51, 17, 4, '2026-09-25', 3, '2026-09-28', '2026-09-25');

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `username`, `password`) VALUES
(1, 'admin', '0192023a7bbd73250516f069df18b500');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_no` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `course` varchar(100) NOT NULL,
  `birthdate` date DEFAULT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_no`, `name`, `course`, `birthdate`, `contact_number`, `is_active`) VALUES
(1, '2021-00001', 'Juan Dela Cruz', 'Juris Doctor', NULL, NULL, 1),
(2, '2021-00002', 'Mariah Santos', 'Juris Doctor', NULL, NULL, 1),
(3, '2021-00003', 'Pedro Reyes', 'Juris Doctor', NULL, NULL, 1),
(4, '2021-00004', 'Ana Gomez Jr.', 'Juris Doctor', NULL, NULL, 1),
(5, '2021-00005', 'Anthony Dances', 'Juris Doctor', NULL, NULL, 1),
(6, '2021-00006', 'Neil Marquez', 'Juris Doctor', NULL, NULL, 1),
(8, '2021-123456', 'werwrwr wrwerw', 'Juris Doctor', NULL, NULL, 1),
(10, '1111111', 'Jojo onthebeat', 'Juris Doctor', NULL, NULL, 1),
(11, '000007', 'ASD', 'Juris Doctor', NULL, NULL, 1),
(12, 'TCM-0002', 'Merry Cris Tobio malasaga IV', 'Juris Doctor', '2004-12-21', '09922801475', 1),
(13, 'TCM-0004', 'Anthony Jess Dances', 'Juris Doctor', '2004-02-21', '', 1),
(14, 'TCM-0009', 'Charie John Pajota II', 'Juris Doctor', '2005-02-12', '09922341234', 1),
(15, 'TCM-0006', 'nior yesman casipe', 'Juris Doctor', '1899-12-21', '09912126112', 1),
(16, 'TCM-0001', 'Neil Tantoy Marquezq', 'Juris Doctor', '2005-11-04', '09922801475', 1),
(17, '20222703', 'Jose Salvador', 'Juris Doctor', NULL, NULL, 1),
(18, '2022', 'Benigno Aquino', 'Juris Doctor', NULL, NULL, 1),
(19, '2024', 'Lapu Lapu', 'Juris Doctor', NULL, NULL, 1),
(20, '2020', 'Jose Rizal', 'Juris Doctor', NULL, NULL, 1),
(21, '20201212', 'Daniel Ceasar', 'Juris Doctor', NULL, NULL, 1),
(22, '2022111', 'Arthur Nery', 'Juris Doctor', NULL, NULL, 1),
(23, '2024222', 'Rex Orange', 'Juris Doctor', NULL, NULL, 1),
(25, '000008', 'Arthur Raquela', 'Juris Doctor', NULL, NULL, 1);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `books`
--
ALTER TABLE `books`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `borrowing`
--
ALTER TABLE `borrowing`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_no` (`student_no`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `books`
--
ALTER TABLE `books`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `borrowing`
--
ALTER TABLE `borrowing`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

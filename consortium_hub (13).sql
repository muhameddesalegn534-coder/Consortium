-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 12, 2025 at 09:18 PM
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
-- Database: `consortium_hub`
--

-- --------------------------------------------------------

--
-- Table structure for table `bank_reconciliation_documents`
--

CREATE TABLE `bank_reconciliation_documents` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `cluster` varchar(100) DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bank_reconciliation_documents`
--

INSERT INTO `bank_reconciliation_documents` (`id`, `user_id`, `cluster`, `file_path`, `file_name`, `uploaded_at`, `description`) VALUES
(1, 2, 'Woldiya', 'uploads/bank_reconciliation/68be79a65719f_Blue and Gray Simple Professional CV Resume (2).pdf', 'Blue and Gray Simple Professional CV Resume (2).pdf', '2025-09-08 06:37:26', NULL),
(2, 1, 'Mekele', 'uploads/bank_reconciliation/68d5279e41e71_certificate_1_2025-08-30_22-16-00_68b35c0063b03.pdf', 'certificate_1_2025-08-30_22-16-00_68b35c0063b03.pdf', '2025-09-25 11:29:34', 'Bank Reconciliation');

-- --------------------------------------------------------

--
-- Table structure for table `budget_data`
--

CREATE TABLE `budget_data` (
  `id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `period_name` varchar(50) NOT NULL,
  `budget` decimal(10,2) DEFAULT NULL,
  `actual` decimal(10,2) DEFAULT NULL,
  `forecast` decimal(10,2) DEFAULT NULL,
  `actual_plus_forecast` decimal(10,2) DEFAULT NULL,
  `variance_percentage` decimal(5,2) DEFAULT NULL,
  `quarter_number` tinyint(4) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `certified` enum('certified','uncertified') DEFAULT 'uncertified',
  `cluster` varchar(100) DEFAULT NULL,
  `year2` int(11) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'ETB'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_data`
--

INSERT INTO `budget_data` (`id`, `year`, `category_name`, `period_name`, `budget`, `actual`, `forecast`, `actual_plus_forecast`, `variance_percentage`, `quarter_number`, `start_date`, `end_date`, `certified`, `cluster`, `year2`, `currency`) VALUES
(377, 1, 'Administrative costs', 'Q3', 1731.01, NULL, NULL, NULL, NULL, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Woldiya', 2025, 'EUR'),
(378, 1, 'Administrative costs', 'Q4', 8479.93, NULL, NULL, NULL, NULL, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Woldiya', 2025, 'EUR'),
(379, 1, 'Administrative costs', 'Q1', 4072.12, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Woldiya', 2026, 'EUR'),
(380, 1, 'Administrative costs', 'Q2', 4072.12, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Woldiya', 2026, 'EUR'),
(381, 1, 'Operational support costs', 'Q3', 12633.47, NULL, NULL, NULL, NULL, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Woldiya', 2025, 'EUR'),
(382, 1, 'Operational support costs', 'Q4', 14066.40, NULL, NULL, NULL, NULL, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Woldiya', 2025, 'EUR'),
(383, 1, 'Operational support costs', 'Q1', 14276.40, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Woldiya', 2026, 'EUR'),
(384, 1, 'Operational support costs', 'Q2', 14066.40, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Woldiya', 2026, 'EUR'),
(385, 1, 'Consortium Activities', 'Q3', 22007.30, NULL, NULL, NULL, NULL, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Woldiya', 2025, 'EUR'),
(386, 1, 'Consortium Activities', 'Q4', 15877.60, NULL, NULL, NULL, NULL, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Woldiya', 2025, 'EUR'),
(387, 1, 'Consortium Activities', 'Q1', 23113.60, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Woldiya', 2026, 'EUR'),
(388, 1, 'Consortium Activities', 'Q2', 14005.90, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Woldiya', 2026, 'EUR'),
(389, 1, 'Targeting new CSOs', 'Q3', 0.00, NULL, NULL, NULL, NULL, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Woldiya', 2025, 'EUR'),
(390, 1, 'Targeting new CSOs', 'Q4', 1035.00, NULL, NULL, NULL, NULL, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Woldiya', 2025, 'EUR'),
(391, 1, 'Targeting new CSOs', 'Q1', 0.00, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Woldiya', 2026, 'EUR'),
(392, 1, 'Targeting new CSOs', 'Q2', 15130.00, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Woldiya', 2026, 'EUR'),
(393, 1, 'Contingency', 'Q3', 1206.70, NULL, NULL, NULL, NULL, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Woldiya', 2025, 'EUR'),
(394, 1, 'Contingency', 'Q4', 837.33, NULL, NULL, NULL, NULL, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Woldiya', 2025, 'EUR'),
(395, 1, 'Contingency', 'Q1', 837.33, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Woldiya', 2026, 'EUR'),
(396, 1, 'Contingency', 'Q2', 837.33, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Woldiya', 2026, 'EUR'),
(397, 1, 'Administrative costs', 'Q3', 1731.01, NULL, NULL, 0.00, 100.00, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Test', 2025, 'EUR'),
(398, 1, 'Administrative costs', 'Q4', 8479.93, 1847.33, 6632.60, 8479.93, 0.00, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Test', 2025, 'EUR'),
(399, 1, 'Administrative costs', 'Q1', 4072.12, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Test', 2026, 'EUR'),
(400, 1, 'Administrative costs', 'Q2', 4072.12, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Test', 2026, 'EUR'),
(401, 1, 'Operational support costs', 'Q3', 12633.47, NULL, NULL, 0.00, 100.00, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Test', 2025, 'EUR'),
(402, 1, 'Operational support costs', 'Q4', 14066.40, NULL, NULL, 0.00, 100.00, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Test', 2025, 'EUR'),
(403, 1, 'Operational support costs', 'Q1', 14276.40, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Test', 2026, 'EUR'),
(404, 1, 'Operational support costs', 'Q2', 14066.40, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Test', 2026, 'EUR'),
(405, 1, 'Consortium Activities', 'Q3', 22007.30, NULL, NULL, 0.00, 100.00, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Test', 2025, 'EUR'),
(406, 1, 'Consortium Activities', 'Q4', 15877.60, NULL, NULL, 0.00, 100.00, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Test', 2025, 'EUR'),
(407, 1, 'Consortium Activities', 'Q1', 23113.60, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Test', 2026, 'EUR'),
(408, 1, 'Consortium Activities', 'Q2', 14005.90, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Test', 2026, 'EUR'),
(409, 1, 'Targeting new CSOs', 'Q3', 0.00, NULL, NULL, 0.00, 0.00, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Test', 2025, 'EUR'),
(410, 1, 'Targeting new CSOs', 'Q4', 1035.00, NULL, NULL, 0.00, 100.00, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Test', 2025, 'EUR'),
(411, 1, 'Targeting new CSOs', 'Q1', 0.00, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Test', 2026, 'EUR'),
(412, 1, 'Targeting new CSOs', 'Q2', 15130.00, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Test', 2026, 'EUR'),
(413, 1, 'Contingency', 'Q3', 1206.70, NULL, NULL, 0.00, 100.00, 0, '2025-07-01', '2025-09-30', 'uncertified', 'Test', 2025, 'EUR'),
(414, 1, 'Contingency', 'Q4', 837.33, NULL, NULL, 0.00, 100.00, 0, '2025-10-01', '2025-12-31', 'uncertified', 'Test', 2025, 'EUR'),
(415, 1, 'Contingency', 'Q1', 837.33, NULL, NULL, NULL, NULL, 0, '2026-01-01', '2026-03-31', 'uncertified', 'Test', 2026, 'EUR'),
(416, 1, 'Contingency', 'Q2', 837.33, NULL, NULL, NULL, NULL, 0, '2026-04-01', '2026-06-30', 'uncertified', 'Test', 2026, 'EUR');

-- --------------------------------------------------------

--
-- Table structure for table `budget_ppreview`
--

CREATE TABLE `budget_ppreview` (
  `PreviewID` int(11) NOT NULL,
  `BudgetHeading` varchar(255) DEFAULT NULL,
  `Outcome` varchar(255) DEFAULT NULL,
  `Activity` varchar(255) DEFAULT NULL,
  `BudgetLine` varchar(255) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Partner` varchar(255) DEFAULT NULL,
  `EntryDate` date DEFAULT NULL,
  `Amount` decimal(18,2) DEFAULT NULL,
  `PVNumber` varchar(50) DEFAULT NULL,
  `Documents` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_preview`
--

CREATE TABLE `budget_preview` (
  `PreviewID` int(11) NOT NULL,
  `BudgetHeading` varchar(255) DEFAULT NULL,
  `Outcome` varchar(255) DEFAULT NULL,
  `Activity` varchar(255) DEFAULT NULL,
  `BudgetLine` varchar(255) DEFAULT NULL,
  `Description` text DEFAULT NULL,
  `Partner` varchar(255) DEFAULT NULL,
  `EntryDate` date DEFAULT NULL,
  `Amount` decimal(18,2) DEFAULT NULL,
  `PVNumber` varchar(50) DEFAULT NULL,
  `Documents` varchar(255) DEFAULT NULL,
  `DocumentPaths` text DEFAULT NULL,
  `DocumentTypes` varchar(500) DEFAULT NULL,
  `OriginalNames` varchar(500) DEFAULT NULL,
  `QuarterPeriod` varchar(10) DEFAULT NULL,
  `CategoryName` varchar(255) DEFAULT NULL,
  `OriginalBudget` decimal(18,2) DEFAULT NULL,
  `RemainingBudget` decimal(18,2) DEFAULT NULL,
  `ActualSpent` decimal(18,2) DEFAULT NULL,
  `VariancePercentage` decimal(5,2) DEFAULT NULL,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cluster` varchar(255) DEFAULT NULL,
  `budget_id` int(11) DEFAULT NULL,
  `ForecastAmount` decimal(18,2) DEFAULT NULL,
  `COMMENTS` varchar(255) DEFAULT NULL,
  `ACCEPTANCE` varchar(255) DEFAULT NULL,
  `currency` varchar(3) DEFAULT 'ETB',
  `use_custom_rate` tinyint(1) NOT NULL DEFAULT 0,
  `usd_to_etb` decimal(18,4) DEFAULT NULL,
  `eur_to_etb` decimal(18,4) DEFAULT NULL,
  `usd_to_eur` decimal(18,4) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `budget_preview`
--

INSERT INTO `budget_preview` (`PreviewID`, `BudgetHeading`, `Outcome`, `Activity`, `BudgetLine`, `Description`, `Partner`, `EntryDate`, `Amount`, `PVNumber`, `Documents`, `DocumentPaths`, `DocumentTypes`, `OriginalNames`, `QuarterPeriod`, `CategoryName`, `OriginalBudget`, `RemainingBudget`, `ActualSpent`, `VariancePercentage`, `CreatedAt`, `UpdatedAt`, `cluster`, `budget_id`, `ForecastAmount`, `COMMENTS`, `ACCEPTANCE`, `currency`, `use_custom_rate`, `usd_to_etb`, `eur_to_etb`, `usd_to_eur`) VALUES
(224, 'Administrative Costs', 'check', 'check', 'check', 'check', 'check', '2025-10-05', 2.67, 'PV -300', NULL, 'admin/documents/68e2d6a8cb20d_1759696552.pdf', 'Withholding_Tax_(WHT)_Payment_Request_Form', 'certificate_1_2025-08-30_22-16-00_68b35c0063b03.pdf', 'Q4', 'Administrative Costs', 8479.93, 8477.26, 2.67, 0.00, '2025-10-05 20:35:59', '2025-10-06 07:39:23', 'Test', 398, 8477.26, '', '1', 'EUR', 0, NULL, NULL, NULL),
(225, 'Administrative Costs', 'check', 'check', 'check', 'check', 'check', '2025-10-05', 3.33, '', NULL, '', '', '', 'Q4', 'Administrative Costs', 8479.93, 8473.93, 6.00, 0.00, '2025-10-05 20:38:05', '2025-10-05 20:38:05', 'Test', 398, 8473.93, '', '', 'EUR', 0, NULL, NULL, NULL),
(226, 'Administrative Costs', 'check', 'check', 'Chcke2', 'check', 'check', '2025-10-05', 4.00, '', NULL, '', '', '', 'Q4', 'Administrative Costs', 8479.93, 8469.93, 10.00, 0.00, '2025-10-05 20:51:32', '2025-10-05 20:51:32', 'Test', 398, 8469.93, '', '', 'EUR', 0, NULL, NULL, NULL),
(227, 'Administrative Costs', 'check', 'check', 'check', 'check', 'check', '2025-10-06', 2.00, '', NULL, '', '', '', 'Q4', 'Administrative Costs', 8479.93, 8467.93, 12.00, 0.00, '2025-10-06 06:14:20', '2025-10-06 06:14:20', 'Test', 398, 8467.93, '', '', 'EUR', 0, NULL, NULL, NULL),
(228, 'Administrative Costs', 'muhamed', 'Ahmed', 'Mohammed', 'hi', '30', '2025-10-12', 2.00, '', NULL, '', '', '', 'Q4', 'Administrative Costs', 8479.93, 8465.93, 14.00, 0.00, '2025-10-12 18:35:28', '2025-10-12 18:35:28', 'Test', 398, 8465.93, '', '', 'EUR', 0, NULL, NULL, NULL),
(229, 'Administrative Costs', 'muhamed', 'Ahmed', 'Mohammed', 'Ahmed', 'AFD', '2025-10-12', 1333.33, '', NULL, '', '', '', 'Q4', 'Administrative Costs', 8479.93, 7132.60, 1347.33, 0.00, '2025-10-12 19:07:01', '2025-10-12 19:07:01', 'Test', 398, 7132.60, '', '', 'EUR', 0, NULL, NULL, NULL),
(230, 'Administrative Costs', 'mamie', 'Ahmed', 'Mohammed', 'Ahmed', 'hi', '2025-10-12', 500.00, '', NULL, '', '', '', 'Q4', 'Administrative Costs', 8479.93, 6632.60, 1847.33, 0.00, '2025-10-12 19:10:38', '2025-10-12 19:10:38', 'Test', 398, 6632.60, '', '', 'EUR', 1, 200.0000, 200.0000, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `id` int(11) NOT NULL,
  `year` int(4) NOT NULL,
  `category_name` varchar(255) NOT NULL,
  `period_name` varchar(50) NOT NULL,
  `budget` decimal(18,2) DEFAULT NULL,
  `actual` decimal(18,2) DEFAULT NULL,
  `forecast` decimal(18,2) DEFAULT NULL,
  `actual_plus_forecast` decimal(18,2) DEFAULT NULL,
  `variance_percentage` decimal(5,2) DEFAULT NULL,
  `quarter_number` int(1) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `certificate_path` varchar(500) NOT NULL,
  `uploaded_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` varchar(255) DEFAULT 'admin',
  `status` enum('active','archived') DEFAULT 'active',
  `cluster` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`id`, `year`, `category_name`, `period_name`, `budget`, `actual`, `forecast`, `actual_plus_forecast`, `variance_percentage`, `quarter_number`, `start_date`, `end_date`, `certificate_path`, `uploaded_date`, `uploaded_by`, `status`, `cluster`) VALUES
(1, 2025, '1. Administrative costs', 'Q1', 712.90, 3100.00, NULL, 3100.00, 334.84, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(2, 2025, '1. Administrative costs', 'Q2', 3592.04, NULL, 3592.04, 3592.04, NULL, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(3, 2025, '1. Administrative costs', 'Q3', 0.00, 30999.91, NULL, 30999.91, 100.00, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(4, 2025, '1. Administrative costs', 'Q4', 3592.04, NULL, NULL, 0.00, NULL, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(5, 2025, '1. Administrative costs', 'Annual Total', 7896.98, 34099.91, 3592.04, 37691.95, 331.81, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(6, 2025, '2. Operational support costs', 'Q1', 13704.93, NULL, NULL, 0.00, NULL, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(7, 2025, '2. Operational support costs', 'Q2', 13284.93, NULL, NULL, 0.00, NULL, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(8, 2025, '2. Operational support costs', 'Q3', 13494.93, NULL, NULL, 0.00, NULL, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(9, 2025, '2. Operational support costs', 'Q4', 13494.93, NULL, NULL, 0.00, NULL, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(10, 2025, '2. Operational support costs', 'Annual Total', 40484.79, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(11, 2025, '3. Consortium Activities', 'Q1', 19358.72, NULL, NULL, 0.00, NULL, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(12, 2025, '3. Consortium Activities', 'Q2', 13800.28, NULL, NULL, 0.00, NULL, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(13, 2025, '3. Consortium Activities', 'Q3', 25845.28, NULL, NULL, 0.00, NULL, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(14, 2025, '3. Consortium Activities', 'Q4', NULL, NULL, NULL, 0.00, 0.00, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(15, 2025, '3. Consortium Activities', 'Annual Total', 59004.29, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(16, 2025, '4. Targeting new CSOs', 'Q1', NULL, NULL, NULL, 0.00, 0.00, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(17, 2025, '4. Targeting new CSOs', 'Q2', NULL, NULL, NULL, 0.00, 0.00, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(18, 2025, '4. Targeting new CSOs', 'Q3', NULL, NULL, NULL, 0.00, 0.00, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(19, 2025, '4. Targeting new CSOs', 'Q4', NULL, NULL, NULL, 0.00, 0.00, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(20, 2025, '4. Targeting new CSOs', 'Annual Total', NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(21, 2025, '5. Contingency', 'Q1', 701.92, NULL, NULL, 0.00, NULL, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(22, 2025, '5. Contingency', 'Q2', 701.92, NULL, NULL, 0.00, NULL, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(23, 2025, '5. Contingency', 'Q3', 701.92, NULL, NULL, 0.00, NULL, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(24, 2025, '5. Contingency', 'Q4', 701.92, NULL, NULL, 0.00, NULL, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(25, 2025, '5. Contingency', 'Annual Total', 2105.76, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(26, 2025, 'Grand Total', 'Overall', 112591.82, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-25-58_68a9cf76e4555.pdf', '2025-08-23 14:25:58', 'admin', 'active', 'Woldiya'),
(32, 2025, '1. Administrative costs', 'Q1', 712.90, 3100.00, NULL, 3100.00, 334.84, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(33, 2025, '1. Administrative costs', 'Q2', 3592.04, NULL, 3592.04, 3592.04, NULL, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(34, 2025, '1. Administrative costs', 'Q3', 0.00, 30999.91, NULL, 30999.91, 100.00, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(35, 2025, '1. Administrative costs', 'Q4', 3592.04, NULL, NULL, 0.00, NULL, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(36, 2025, '1. Administrative costs', 'Annual Total', 7896.98, 34099.91, 3592.04, 37691.95, 331.81, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(37, 2025, '2. Operational support costs', 'Q1', 13704.93, NULL, NULL, 0.00, NULL, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(38, 2025, '2. Operational support costs', 'Q2', 13284.93, NULL, NULL, 0.00, NULL, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(39, 2025, '2. Operational support costs', 'Q3', 13494.93, NULL, NULL, 0.00, NULL, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(40, 2025, '2. Operational support costs', 'Q4', 13494.93, NULL, NULL, 0.00, NULL, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(41, 2025, '2. Operational support costs', 'Annual Total', 40484.79, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(42, 2025, '3. Consortium Activities', 'Q1', 19358.72, NULL, NULL, 0.00, NULL, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(43, 2025, '3. Consortium Activities', 'Q2', 13800.28, NULL, NULL, 0.00, NULL, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(44, 2025, '3. Consortium Activities', 'Q3', 25845.28, NULL, NULL, 0.00, NULL, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(45, 2025, '3. Consortium Activities', 'Q4', NULL, NULL, NULL, 0.00, 0.00, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(46, 2025, '3. Consortium Activities', 'Annual Total', 59004.29, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(47, 2025, '4. Targeting new CSOs', 'Q1', NULL, NULL, NULL, 0.00, 0.00, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(48, 2025, '4. Targeting new CSOs', 'Q2', NULL, NULL, NULL, 0.00, 0.00, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(49, 2025, '4. Targeting new CSOs', 'Q3', NULL, NULL, NULL, 0.00, 0.00, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(50, 2025, '4. Targeting new CSOs', 'Q4', NULL, NULL, NULL, 0.00, 0.00, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(51, 2025, '4. Targeting new CSOs', 'Annual Total', NULL, NULL, NULL, NULL, 0.00, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(52, 2025, '5. Contingency', 'Q1', 701.92, NULL, NULL, 0.00, NULL, 1, '2025-07-01', '2025-09-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(53, 2025, '5. Contingency', 'Q2', 701.92, NULL, NULL, 0.00, NULL, 2, '2025-10-01', '2025-12-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(54, 2025, '5. Contingency', 'Q3', 701.92, NULL, NULL, 0.00, NULL, 3, '2026-01-01', '2026-03-31', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(55, 2025, '5. Contingency', 'Q4', 701.92, NULL, NULL, 0.00, NULL, 4, '2026-04-01', '2026-06-30', 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(56, 2025, '5. Contingency', 'Annual Total', 2105.76, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya'),
(57, 2025, 'Grand Total', 'Overall', 112591.82, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin/uploads/certificates/certificate_2025_2025-08-23_16-27-41_68a9cfdd54c0a.pdf', '2025-08-23 14:27:41', 'admin', 'active', 'Woldiya');

-- --------------------------------------------------------

--
-- Table structure for table `certificates_simple`
--

CREATE TABLE `certificates_simple` (
  `id` int(11) NOT NULL,
  `cluster_name` varchar(100) NOT NULL,
  `year` int(4) NOT NULL,
  `certificate_path` varchar(500) NOT NULL,
  `uploaded_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` varchar(255) DEFAULT 'admin'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates_simple`
--

INSERT INTO `certificates_simple` (`id`, `cluster_name`, `year`, `certificate_path`, `uploaded_date`, `uploaded_by`) VALUES
(1, 'Woldiya', 1, 'admin/uploads/certificates/certificate_1_2025-08-30_22-16-00_68b35c0063b03.pdf', '2025-08-30 20:16:00', 'admin');

-- --------------------------------------------------------

--
-- Table structure for table `checklist_items`
--

CREATE TABLE `checklist_items` (
  `id` int(11) NOT NULL,
  `category` varchar(255) NOT NULL,
  `document_name` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `checklist_items`
--

INSERT INTO `checklist_items` (`id`, `category`, `document_name`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(117, '1 Withholding Tax (WHT) Payments', 'Withholding Tax (WHT) Payment Request Form', 1, 1, '2025-10-05 15:48:15', '2025-10-05 15:48:15'),
(118, '1 Withholding Tax (WHT) Payments', 'Withholding Tax (WHT) Calculation Sheet', 2, 1, '2025-10-05 15:48:15', '2025-10-05 15:48:15'),
(119, '1 Withholding Tax (WHT) Payments', 'Payment Voucher', 3, 1, '2025-10-05 15:48:16', '2025-10-05 15:48:16'),
(120, '1 Withholding Tax (WHT) Payments', 'Bank Transfer Request Letter / Cheque Copy', 4, 1, '2025-10-05 15:48:16', '2025-10-05 15:48:16'),
(121, '1 Withholding Tax (WHT) Payments', 'Proof of Payment (Bank Transfer Confirmation / Cheque Copy)', 5, 1, '2025-10-05 15:48:17', '2025-10-05 15:48:17'),
(122, '2 Income Tax Payments', 'Income Tax Payment Request Form', 1, 1, '2025-10-05 15:48:18', '2025-10-05 15:48:18'),
(123, '2 Income Tax Payments', 'Income Tax Calculation Sheet', 2, 1, '2025-10-05 15:48:18', '2025-10-05 15:48:18'),
(124, '2 Income Tax Payments', 'Payment Voucher', 3, 1, '2025-10-05 15:48:18', '2025-10-05 15:48:18'),
(125, '2 Income Tax Payments', 'Bank Transfer Request Letter / Cheque Copy', 4, 1, '2025-10-05 15:48:19', '2025-10-05 15:48:19'),
(126, '2 Income Tax Payments', 'Proof of Payment (Bank Transfer Confirmation / Cheque Copy)', 5, 1, '2025-10-05 15:48:19', '2025-10-05 15:48:19'),
(127, '3 Pension Contribution Payment', 'Pension Calculation Sheet', 1, 1, '2025-10-05 15:48:19', '2025-10-05 15:48:19'),
(128, '3 Pension Contribution Payment', 'Pension Payment Slip / Receipt from Tax Authority', 2, 1, '2025-10-05 15:48:19', '2025-10-05 15:48:19'),
(129, '3 Pension Contribution Payment', 'Bank Confirmation of Pension Payment', 3, 1, '2025-10-05 15:48:20', '2025-10-05 15:48:20'),
(130, '4 Payroll Payments', 'Approved Timesheets / Attendance Records', 1, 1, '2025-10-05 15:48:20', '2025-10-05 15:48:20'),
(131, '4 Payroll Payments', 'Payroll Register Sheet ( For Each Project )', 2, 1, '2025-10-05 15:48:20', '2025-10-05 15:48:20'),
(132, '4 Payroll Payments', 'Master Payroll Register Sheet', 3, 1, '2025-10-05 15:48:20', '2025-10-05 15:48:20'),
(133, '4 Payroll Payments', 'Payslips / Pay Stubs (for each employee) ( If applicable)', 4, 1, '2025-10-05 15:48:20', '2025-10-05 15:48:20'),
(134, '4 Payroll Payments', 'Bank Transfer Request Letter', 5, 1, '2025-10-05 15:48:21', '2025-10-05 15:48:21'),
(135, '4 Payroll Payments', 'Proof Of Payment', 6, 1, '2025-10-05 15:48:21', '2025-10-05 15:48:21'),
(136, '4 Payroll Payments', 'Payment Voucher', 7, 1, '2025-10-05 15:48:21', '2025-10-05 15:48:21'),
(137, '5 Telecom Services Payments', 'Telecom Service Contract / Agreement (if applicable)', 1, 1, '2025-10-05 15:48:21', '2025-10-05 15:48:21'),
(138, '5 Telecom Services Payments', 'Monthly Telecom Bill / Invoice', 2, 1, '2025-10-05 15:48:22', '2025-10-05 15:48:22'),
(139, '5 Telecom Services Payments', 'Cost Pro-ration Sheet', 3, 1, '2025-10-05 15:48:22', '2025-10-05 15:48:22'),
(140, '5 Telecom Services Payments', 'Payment Request Form (Approved (by authorized person) )', 4, 1, '2025-10-05 15:48:22', '2025-10-05 15:48:22'),
(141, '5 Telecom Services Payments', 'Bank transfer Request Letter /Cheque copy', 5, 1, '2025-10-05 15:48:22', '2025-10-05 15:48:22'),
(142, '5 Telecom Services Payments', 'Proof of Payment (Bank transfer confirmation/Cheque copy)', 6, 1, '2025-10-05 15:48:23', '2025-10-05 15:48:23'),
(143, '6 Rent Payments', 'Rental / Lease Agreement', 1, 1, '2025-10-05 15:48:23', '2025-10-05 15:48:23'),
(144, '6 Rent Payments', 'Landlord\'s Invoice / Payment Request', 2, 1, '2025-10-05 15:48:23', '2025-10-05 15:48:23'),
(145, '6 Rent Payments', 'Payment Request Form (Approved (by authorized person) )', 3, 1, '2025-10-05 15:48:23', '2025-10-05 15:48:23'),
(146, '6 Rent Payments', 'Cost Pro-ration Sheet', 4, 1, '2025-10-05 15:48:24', '2025-10-05 15:48:24'),
(147, '6 Rent Payments', 'Bank transfer Request Letter /Cheque copy', 5, 1, '2025-10-05 15:48:24', '2025-10-05 15:48:24'),
(148, '6 Rent Payments', 'Proof of Payment (Bank transfer Advice /Cheque copy)', 6, 1, '2025-10-05 15:48:24', '2025-10-05 15:48:24'),
(149, '6 Rent Payments', 'Withholding Tax (WHT) Receipt (if applicable)', 7, 1, '2025-10-05 15:48:24', '2025-10-05 15:48:24'),
(150, '7 Consultant Payments', 'Consultant Service Contract Agreement', 1, 1, '2025-10-05 15:48:24', '2025-10-05 15:48:24'),
(151, '7 Consultant Payments', 'Scope of Work (SOW) / Terms of Reference (TOR)', 2, 1, '2025-10-05 15:48:25', '2025-10-05 15:48:25'),
(152, '7 Consultant Payments', 'Consultant Invoice (if applicable)', 3, 1, '2025-10-05 15:48:25', '2025-10-05 15:48:25'),
(153, '7 Consultant Payments', 'Consultant Service accomplishment Activity report / Progress Report', 4, 1, '2025-10-05 15:48:26', '2025-10-05 15:48:26'),
(154, '7 Consultant Payments', 'Payment Request Form (Approved)', 5, 1, '2025-10-05 15:48:26', '2025-10-05 15:48:26'),
(155, '7 Consultant Payments', 'Proof of Payment (Bank transfer confirmation/Cheque copy)', 6, 1, '2025-10-05 15:48:26', '2025-10-05 15:48:26'),
(156, '7 Consultant Payments', 'Withholding Tax (WHT) Receipt (if applicable)', 7, 1, '2025-10-05 15:48:26', '2025-10-05 15:48:26'),
(157, '7 Consultant Payments', 'Paymnet Voucher', 8, 1, '2025-10-05 15:48:27', '2025-10-05 15:48:27'),
(158, '8 Freight Transportation', 'Purchase request', 1, 1, '2025-10-05 15:48:27', '2025-10-05 15:48:27'),
(159, '8 Freight Transportation', 'Quotation request (filled in and sent to suppliers)', 2, 1, '2025-10-05 15:48:27', '2025-10-05 15:48:27'),
(160, '8 Freight Transportation', 'Quotation (received back, signed and stamped)', 3, 1, '2025-10-05 15:48:28', '2025-10-05 15:48:28'),
(161, '8 Freight Transportation', 'Attached proforma invoices in a sealed envelope', 4, 1, '2025-10-05 15:48:28', '2025-10-05 15:48:28'),
(162, '8 Freight Transportation', 'Proformas with all formalities, including trade license', 5, 1, '2025-10-05 15:48:28', '2025-10-05 15:48:28'),
(163, '8 Freight Transportation', 'Competitive bid analysis (CBA) signed and approved', 6, 1, '2025-10-05 15:48:28', '2025-10-05 15:48:28'),
(164, '8 Freight Transportation', 'Contract agreement or purchase order', 7, 1, '2025-10-05 15:48:28', '2025-10-05 15:48:28'),
(165, '8 Freight Transportation', 'Payment request form', 8, 1, '2025-10-05 15:48:29', '2025-10-05 15:48:29'),
(166, '8 Freight Transportation', 'Original waybill', 9, 1, '2025-10-05 15:48:29', '2025-10-05 15:48:29'),
(167, '8 Freight Transportation', 'Goods received notes', 10, 1, '2025-10-05 15:48:29', '2025-10-05 15:48:29'),
(168, '8 Freight Transportation', 'Cash receipt invoice (with Organizational TIN)', 11, 1, '2025-10-05 15:48:29', '2025-10-05 15:48:29'),
(169, '8 Freight Transportation', 'Cheque copy or bank transfer letter from vendor', 12, 1, '2025-10-05 15:48:30', '2025-10-05 15:48:30'),
(170, '8 Freight Transportation', 'Payment voucher', 13, 1, '2025-10-05 15:48:30', '2025-10-05 15:48:30'),
(171, '9 Vehicle Rental', 'Purchase request for rental service', 1, 1, '2025-10-05 15:48:30', '2025-10-05 15:48:30'),
(172, '9 Vehicle Rental', 'Quotation request (filled in and sent to suppliers)', 2, 1, '2025-10-05 15:48:30', '2025-10-05 15:48:30'),
(173, '9 Vehicle Rental', 'Quotation (received back, signed and stamped)', 3, 1, '2025-10-05 15:48:31', '2025-10-05 15:48:31'),
(174, '9 Vehicle Rental', 'Attached proforma invoices in a sealed envelope', 4, 1, '2025-10-05 15:48:31', '2025-10-05 15:48:31'),
(175, '9 Vehicle Rental', 'Proformas with all formalities, including trade license', 5, 1, '2025-10-05 15:48:31', '2025-10-05 15:48:31'),
(176, '9 Vehicle Rental', 'Competitive bid analysis (CBA) signed and approved', 6, 1, '2025-10-05 15:48:31', '2025-10-05 15:48:31'),
(177, '9 Vehicle Rental', 'Contract agreement or purchase order', 7, 1, '2025-10-05 15:48:32', '2025-10-05 15:48:32'),
(178, '9 Vehicle Rental', 'Payment request form', 8, 1, '2025-10-05 15:48:32', '2025-10-05 15:48:32'),
(179, '9 Vehicle Rental', 'Summary of payments sheet', 9, 1, '2025-10-05 15:48:33', '2025-10-05 15:48:33'),
(180, '9 Vehicle Rental', 'Signed and approved log book sheet', 10, 1, '2025-10-05 15:48:34', '2025-10-05 15:48:34'),
(181, '9 Vehicle Rental', 'Vehicle goods-outward inspection certificate', 11, 1, '2025-10-05 15:48:35', '2025-10-05 15:48:35'),
(182, '9 Vehicle Rental', 'Withholding receipt (for amounts over ETB 10,000)', 12, 1, '2025-10-05 15:48:35', '2025-10-05 15:48:35'),
(183, '9 Vehicle Rental', 'Cash receipt invoice (with Organizational TIN)', 13, 1, '2025-10-05 15:48:35', '2025-10-05 15:48:35'),
(184, '9 Vehicle Rental', 'Cheque copy or bank transfer letter from vendor', 14, 1, '2025-10-05 15:48:36', '2025-10-05 15:48:36'),
(185, '9 Vehicle Rental', 'Payment voucher', 15, 1, '2025-10-05 15:48:37', '2025-10-05 15:48:37'),
(186, '10 Training, Workshop and Related', 'Training approved by Program Manager', 1, 1, '2025-10-05 15:48:37', '2025-10-05 15:48:37'),
(187, '10 Training, Workshop and Related', 'Participant invitation letters from government parties', 2, 1, '2025-10-05 15:48:38', '2025-10-05 15:48:38'),
(188, '10 Training, Workshop and Related', 'Fully completed attendance sheet', 3, 1, '2025-10-05 15:48:38', '2025-10-05 15:48:38'),
(189, '10 Training, Workshop and Related', 'Manager\'s signature', 4, 1, '2025-10-05 15:48:39', '2025-10-05 15:48:39'),
(190, '10 Training, Workshop and Related', 'Approved payment rate (or justified reason for a different rate)', 5, 1, '2025-10-05 15:48:40', '2025-10-05 15:48:40'),
(191, '10 Training, Workshop and Related', 'Letter from government for fuel (if applicable)', 6, 1, '2025-10-05 15:48:40', '2025-10-05 15:48:40'),
(192, '10 Training, Workshop and Related', 'Activity (training) report', 7, 1, '2025-10-05 15:48:40', '2025-10-05 15:48:40'),
(193, '10 Training, Workshop and Related', 'Cash receipt or bank advice (if refund applicable)', 8, 1, '2025-10-05 15:48:41', '2025-10-05 15:48:41'),
(194, '10 Training, Workshop and Related', 'Expense settlement sheet with all information', 9, 1, '2025-10-05 15:48:41', '2025-10-05 15:48:41'),
(195, '10 Training, Workshop and Related', 'All required signatures on templates', 10, 1, '2025-10-05 15:48:41', '2025-10-05 15:48:41'),
(196, '10 Training, Workshop and Related', 'All documents stamped \"paid\"', 11, 1, '2025-10-05 15:48:42', '2025-10-05 15:48:42'),
(197, '10 Training, Workshop and Related', 'All documents are original (or cross-referenced if not)', 12, 1, '2025-10-05 15:48:42', '2025-10-05 15:48:42'),
(198, '10 Training, Workshop and Related', 'TIN and company name on receipt', 13, 1, '2025-10-05 15:48:42', '2025-10-05 15:48:42'),
(199, '10 Training, Workshop and Related', 'Check dates and all information on receipts', 14, 1, '2025-10-05 15:48:43', '2025-10-05 15:48:43'),
(200, '11 Procurement of Services', 'Purchase requisition', 1, 1, '2025-10-05 15:48:43', '2025-10-05 15:48:43'),
(201, '11 Procurement of Services', 'Quotation request (filled in and sent to suppliers)', 2, 1, '2025-10-05 15:48:44', '2025-10-05 15:48:44'),
(202, '11 Procurement of Services', 'Quotation (received back, signed and stamped)', 3, 1, '2025-10-05 15:48:44', '2025-10-05 15:48:44'),
(203, '11 Procurement of Services', 'Attached proforma invoices in a sealed envelope', 4, 1, '2025-10-05 15:48:44', '2025-10-05 15:48:44'),
(204, '11 Procurement of Services', 'Proformas with all formalities, including trade license', 5, 1, '2025-10-05 15:48:44', '2025-10-05 15:48:44'),
(205, '11 Procurement of Services', 'Competitive bid analysis (CBA) signed and approved', 6, 1, '2025-10-05 15:48:45', '2025-10-05 15:48:45'),
(206, '11 Procurement of Services', 'Contract agreement or purchase order', 7, 1, '2025-10-05 15:48:45', '2025-10-05 15:48:45'),
(207, '11 Procurement of Services', 'Payment request form', 8, 1, '2025-10-05 15:48:46', '2025-10-05 15:48:46'),
(208, '11 Procurement of Services', 'Withholding receipt (for amounts over ETB 10,000)', 9, 1, '2025-10-05 15:48:46', '2025-10-05 15:48:46'),
(209, '11 Procurement of Services', 'Cash receipt invoice (with Organizational TIN)', 10, 1, '2025-10-05 15:48:46', '2025-10-05 15:48:46'),
(210, '11 Procurement of Services', 'Service accomplishment report', 11, 1, '2025-10-05 15:48:47', '2025-10-05 15:48:47'),
(211, '11 Procurement of Services', 'Cheque copy or bank transfer letter from vendor', 12, 1, '2025-10-05 15:48:47', '2025-10-05 15:48:47'),
(212, '11 Procurement of Services', 'Payment voucher', 13, 1, '2025-10-05 15:48:47', '2025-10-05 15:48:47'),
(213, '12 Procurement of Goods', 'Purchase request', 1, 1, '2025-10-05 15:48:48', '2025-10-05 15:48:48'),
(214, '12 Procurement of Goods', 'Quotation request (filled in and sent to suppliers)', 2, 1, '2025-10-05 15:48:48', '2025-10-05 15:48:48'),
(215, '12 Procurement of Goods', 'Quotation (received back, signed and stamped)', 3, 1, '2025-10-05 15:48:48', '2025-10-05 15:48:48'),
(216, '12 Procurement of Goods', 'Attached proforma invoices in a sealed envelope', 4, 1, '2025-10-05 15:48:49', '2025-10-05 15:48:49'),
(217, '12 Procurement of Goods', 'Proformas with all formalities, including trade license', 5, 1, '2025-10-05 15:48:50', '2025-10-05 15:48:50'),
(218, '12 Procurement of Goods', 'Competitive bid analysis (CBA) signed and approved', 6, 1, '2025-10-05 15:48:50', '2025-10-05 15:48:50'),
(219, '12 Procurement of Goods', 'purchase order', 7, 1, '2025-10-05 15:48:50', '2025-10-05 15:48:50'),
(220, '12 Procurement of Goods', 'Contract agreement or Framework Agreement', 8, 1, '2025-10-05 15:48:50', '2025-10-05 15:48:50'),
(221, '12 Procurement of Goods', 'Payment request form', 9, 1, '2025-10-05 15:48:51', '2025-10-05 15:48:51'),
(222, '12 Procurement of Goods', 'Withholding receipt (for amounts over ETB 20,000)', 10, 1, '2025-10-05 15:48:51', '2025-10-05 15:48:51'),
(223, '12 Procurement of Goods', 'Cash receipt invoice (with Organizational TIN)', 11, 1, '2025-10-05 15:48:51', '2025-10-05 15:48:51'),
(224, '12 Procurement of Goods', 'Goods received note (GRN) or delivery note', 12, 1, '2025-10-05 15:48:52', '2025-10-05 15:48:52'),
(225, '12 Procurement of Goods', 'Cheque copy or bank transfer letter from vendor', 13, 1, '2025-10-05 15:48:52', '2025-10-05 15:48:52'),
(226, '12 Procurement of Goods', 'Payment voucher', 14, 1, '2025-10-05 15:48:52', '2025-10-05 15:48:52');

-- --------------------------------------------------------

--
-- Table structure for table `clusters`
--

CREATE TABLE `clusters` (
  `id` int(11) NOT NULL,
  `cluster_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `custom_currency_rate` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clusters`
--

INSERT INTO `clusters` (`id`, `cluster_name`, `description`, `is_active`, `created_at`, `updated_at`, `custom_currency_rate`) VALUES
(8, 'Woldiya', 'Consortium', 1, '2025-10-05 15:57:26', '2025-10-12 19:03:46', 1),
(9, 'Test', 'Test', 1, '2025-10-05 20:31:44', '2025-10-12 19:09:46', 1),
(10, 'NED', 'Head Office', 1, '2025-10-12 18:16:26', '2025-10-12 18:16:26', 0),
(11, 'AFD', 'Head Office', 1, '2025-10-12 18:17:46', '2025-10-12 18:36:07', 1),
(12, 'Addis Ababa', 'Head Office', 1, '2025-10-12 18:56:34', '2025-10-12 18:56:34', 0);

-- --------------------------------------------------------

--
-- Table structure for table `currency_rates`
--

CREATE TABLE `currency_rates` (
  `id` int(11) NOT NULL,
  `cluster_id` int(11) NOT NULL,
  `from_currency` varchar(3) NOT NULL,
  `to_currency` varchar(3) NOT NULL,
  `exchange_rate` decimal(10,4) NOT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currency_rates`
--

INSERT INTO `currency_rates` (`id`, `cluster_id`, `from_currency`, `to_currency`, `exchange_rate`, `last_updated`, `updated_by`, `is_active`) VALUES
(17, 8, 'USD', 'ETB', 141.0000, '2025-10-12 18:12:04', 1, 0),
(18, 8, 'EUR', 'ETB', 150.0000, '2025-10-12 18:12:04', 1, 0),
(19, 8, 'EUR', 'USD', 2.0000, '2025-10-12 18:12:04', 1, 0),
(20, 9, 'USD', 'ETB', 140.0000, '2025-10-05 20:32:20', 1, 1),
(21, 9, 'EUR', 'ETB', 150.0000, '2025-10-05 20:32:32', 1, 1),
(22, 9, 'EUR', 'USD', 2.0000, '2025-10-05 20:32:44', 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_id`, `recipient_id`, `subject`, `message`, `is_read`, `created_at`) VALUES
(1, 1, 2, 'Test Subject', 'This is a test message', 1, '2025-09-15 06:33:23'),
(2, 1, NULL, 'Test Subject', 'This is a test message inserted directly into the database.', 0, '2025-09-15 07:10:33'),
(3, 1, NULL, 'Test Message', 'This is a test message to verify the table works correctly.', 0, '2025-09-15 07:28:22'),
(4, 1, NULL, 'muhamed', 'muhamed', 1, '2025-09-15 07:44:42'),
(5, 1, NULL, 'Test Message 1', 'This is a test message number 1 for testing the message counter in the sidebar.', 1, '2025-09-15 09:04:01'),
(6, 1, NULL, 'Test Message 2', 'This is a test message number 2 for testing the message counter in the sidebar.', 0, '2025-09-15 09:04:01'),
(7, 1, NULL, 'Test Message 3', 'This is a test message number 3 for testing the message counter in the sidebar.', 0, '2025-09-15 09:04:01'),
(8, 2, NULL, 'hi', 'hi there', 0, '2025-09-23 06:59:13'),
(9, 2, NULL, 'check message', 'message check', 1, '2025-09-23 12:42:39');

-- --------------------------------------------------------

--
-- Table structure for table `predefined_fields`
--

CREATE TABLE `predefined_fields` (
  `id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_type` enum('dropdown','input') NOT NULL DEFAULT 'dropdown',
  `field_values` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cluster_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `predefined_fields`
--

INSERT INTO `predefined_fields` (`id`, `field_name`, `field_type`, `field_values`, `is_active`, `created_at`, `updated_at`, `cluster_name`) VALUES
(44, 'BudgetHeading', 'dropdown', 'Administrative Costs,Operational Costs,Consortium Activities,Targeting New CSOs,Contingency', 1, '2025-10-05 18:01:20', '2025-10-05 18:41:42', 'Woldiya'),
(45, 'BudgetHeading', 'dropdown', 'Administrative Costs,Operational Costs,Consortium Activities,Targeting New CSOs,Contingency', 1, '2025-10-05 20:32:03', '2025-10-05 20:32:03', 'Test');

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` int(11) NOT NULL,
  `project_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('planning','active','on_hold','completed','cancelled') NOT NULL DEFAULT 'planning',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_documents`
--

CREATE TABLE `project_documents` (
  `id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `custom_document_name` varchar(255) DEFAULT NULL,
  `cluster` varchar(100) NOT NULL,
  `document_file_names` text DEFAULT NULL,
  `document_file_paths` text DEFAULT NULL,
  `image_file_names` text DEFAULT NULL,
  `image_file_paths` text DEFAULT NULL,
  `progress_title` varchar(255) DEFAULT NULL,
  `progress_date` date DEFAULT NULL,
  `progress_summary` text DEFAULT NULL,
  `progress_details` text DEFAULT NULL,
  `challenge_title` varchar(255) DEFAULT NULL,
  `challenge_description` text DEFAULT NULL,
  `challenge_impact` text DEFAULT NULL,
  `proposed_solution` text DEFAULT NULL,
  `success_title` varchar(255) DEFAULT NULL,
  `success_description` text DEFAULT NULL,
  `beneficiaries` int(11) DEFAULT NULL,
  `success_date` date DEFAULT NULL,
  `uploaded_by` varchar(100) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `photo_titles` text DEFAULT NULL,
  `financial_report_file_names` text DEFAULT NULL,
  `financial_report_file_paths` text DEFAULT NULL,
  `expenditure_issues` text DEFAULT NULL,
  `summary_achievements` text DEFAULT NULL,
  `operating_context` text DEFAULT NULL,
  `outcomes_outputs` text DEFAULT NULL,
  `results_framework_file_names` text DEFAULT NULL,
  `results_framework_file_paths` text DEFAULT NULL,
  `challenges_description` text DEFAULT NULL,
  `mitigation_measures` text DEFAULT NULL,
  `risk_matrix_file_names` text DEFAULT NULL,
  `risk_matrix_file_paths` text DEFAULT NULL,
  `good_practices` text DEFAULT NULL,
  `spotlight_narrative` text DEFAULT NULL,
  `spotlight_photo_file_names` text DEFAULT NULL,
  `spotlight_photo_file_paths` text DEFAULT NULL,
  `other_title` varchar(255) DEFAULT NULL,
  `other_date` date DEFAULT NULL,
  `other_file_names` text DEFAULT NULL,
  `other_file_paths` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `project_documents`
--

INSERT INTO `project_documents` (`id`, `document_type`, `custom_document_name`, `cluster`, `document_file_names`, `document_file_paths`, `image_file_names`, `image_file_paths`, `progress_title`, `progress_date`, `progress_summary`, `progress_details`, `challenge_title`, `challenge_description`, `challenge_impact`, `proposed_solution`, `success_title`, `success_description`, `beneficiaries`, `success_date`, `uploaded_by`, `uploaded_at`, `updated_at`, `photo_titles`, `financial_report_file_names`, `financial_report_file_paths`, `expenditure_issues`, `summary_achievements`, `operating_context`, `outcomes_outputs`, `results_framework_file_names`, `results_framework_file_paths`, `challenges_description`, `mitigation_measures`, `risk_matrix_file_names`, `risk_matrix_file_paths`, `good_practices`, `spotlight_narrative`, `spotlight_photo_file_names`, `spotlight_photo_file_paths`, `other_title`, `other_date`, `other_file_names`, `other_file_paths`) VALUES
(1, 'Progress Report', NULL, 'Woldiya', '[\"PROGEDI_Baseline Assessment Report_EN.pdf\"]', '[\"uploads\\/documents\\/68b347db8e1fa_1756579803.pdf\"]', '[\"1.3 Objectives of the Baseline - visual selection.png\",\"Context and Target Population Profile - visual selection (1).png\",\"Context and Target Population Profile - visual selection.png\"]', '[\"uploads\\/images\\/68b347db8e397_1756579803.png\",\"uploads\\/images\\/68b347db8e4e4_1756579803.png\",\"uploads\\/images\\/68b347db9167d_1756579803.png\"]', 'muhamed', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 18:50:03', '2025-08-30 18:50:03', '[\"mamila\",\"mamila\",\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\",\"Context and Target Population Profile - visual selection.png\"]', '[\"uploads\\/images\\/68b34877d84a0_1756579959.png\",\"uploads\\/images\\/68b34877d8709_1756579959.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 18:52:39', '2025-08-30 18:52:39', '[\"hi\",\"hi\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'Financial Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\",\"Context and Target Population Profile - visual selection.png\"]', '[\"uploads\\/images\\/68b34b99bbfa5_1756580761.png\",\"uploads\\/images\\/68b34b99bc0b2_1756580761.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:06:01', '2025-08-30 19:06:01', '[\"hi\",\"hi\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\"]', '[\"uploads\\/images\\/68b34ba782f11_1756580775.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:06:15', '2025-08-30 19:06:15', '[\"mamie\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\"]', '[\"uploads\\/images\\/68b34c3ed9f6a_1756580926.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:08:46', '2025-08-30 19:08:46', '[\"hi\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\"]', '[\"uploads\\/images\\/68b34d3cd18fa_1756581180.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:13:00', '2025-08-30 19:13:00', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\"]', '[\"uploads\\/images\\/68b34dd399682_1756581331.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:15:31', '2025-08-30 19:15:31', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection.png\"]', '[\"uploads\\/images\\/68b34e503161b_1756581456.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:17:36', '2025-08-30 19:17:36', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(9, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\"]', '[\"uploads\\/images\\/68b34e8702ba1_1756581511.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:18:31', '2025-08-30 19:18:31', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(10, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\"]', '[\"uploads\\/images\\/68b34e99c88ce_1756581529.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:18:49', '2025-08-30 19:18:49', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection.png\"]', '[\"uploads\\/images\\/68b34eb3f4127_1756581555.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:19:16', '2025-08-30 19:19:16', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection.png\"]', '[\"uploads\\/images\\/68b34efb231db_1756581627.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', 'mamie', 'hi', 200, '2025-08-30', 'woldiya_finance', '2025-08-30 19:20:27', '2025-08-30 19:20:27', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'Progress Report', NULL, 'Woldiya', '[\"PROGEDI_Baseline Assessment Report_EN.pdf\"]', '[\"uploads\\/documents\\/68b34f2c49492_1756581676.pdf\"]', '[\"Context and Target Population Profile - visual selection.png\"]', '[\"uploads\\/images\\/68b34f2c49718_1756581676.png\"]', 'hi', '2025-08-30', NULL, NULL, '', '', '', '', 'mamie', 'hi', 200, '2025-08-30', 'woldiya_finance', '2025-08-30 19:21:16', '2025-08-30 19:21:16', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'Progress Report', NULL, 'Woldiya', '[\"PROGEDI_Baseline Assessment Report_EN.pdf\"]', '[\"uploads\\/documents\\/68b34f4d00d0d_1756581709.pdf\"]', '[\"Context and Target Population Profile - visual selection.png\"]', '[\"uploads\\/images\\/68b34f4d00e02_1756581709.png\"]', 'hi', '2025-08-30', NULL, NULL, '', '', '', '', 'mamie', 'hi', 200, '2025-08-30', 'woldiya_finance', '2025-08-30 19:21:49', '2025-08-30 19:21:49', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"ChatGPT Image Aug 25, 2025, 01_07_08 PM.png\"]', '[\"uploads\\/images\\/68b34f57dd875_1756581719.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:21:59', '2025-08-30 19:21:59', '[\"muhamed\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\"]', '[\"uploads\\/images\\/68b3500f70da0_1756581903.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:25:03', '2025-08-30 19:25:03', '[\"hi\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"4.1 Study Design - visual selection.png\"]', '[\"uploads\\/images\\/68b35061dd5fa_1756581985.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:26:25', '2025-08-30 19:26:25', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(18, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[]', '[]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:29:58', '2025-08-30 19:29:58', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\"]', '[\"uploads\\/images\\/68b35141bce78_1756582209.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:30:09', '2025-08-30 19:30:09', '[\"\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[\"Context and Target Population Profile - visual selection (1).png\"]', '[\"uploads\\/images\\/68b35247bdd1b_1756582471.png\"]', '', '2025-08-30', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-08-30 19:34:31', '2025-08-30 19:34:31', '[\"mamila\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'Progress Report', NULL, 'Woldiya', '[\"Copy of Baseline Assessment Report.pdf\"]', '[\"uploads\\/documents\\/68d23d634a5f3_1758608739.pdf\"]', '[\"Capture.PNG\",\"Capture2.PNG\",\"Capture3.PNG\"]', '[\"uploads\\/images\\/68d23d634a6f8_1758608739.PNG\",\"uploads\\/images\\/68d23d634a7a3_1758608739.PNG\",\"uploads\\/images\\/68d23d634a830_1758608739.PNG\"]', 'Report', '2025-09-23', NULL, NULL, '', '', '', '', '', '', 0, '0000-00-00', 'woldiya_finance', '2025-09-23 06:25:39', '2025-09-23 06:25:39', '[\"Photos which show the community supporting the Abegar initiative.\",\"Photos which show the community supporting the Abegar initiative.\",\"Photos which show the community supporting the Abegar initiative.\"]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'Progress Report', NULL, 'Mekele', '[]', '[]', '[]', '[]', '', '2025-09-24', NULL, NULL, 'muhamed', 'hi', 'muhamed', 'mamila', '', '', 0, '0000-00-00', 'admin', '2025-09-24 09:01:31', '2025-09-24 09:01:31', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'financial_report', NULL, 'Woldiya', '[]', '[]', '[]', '[]', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'woldiya_finance', '2025-10-01 21:36:10', '2025-10-01 21:36:10', '[]', '[\"certificate_1_2025-08-30_22-16-00_68b35c0063b03.pdf\"]', '[\"uploads\\/documents\\/68dd9eca669bb_1759354570.pdf\"]', 'These can be things such as:\r\n\r\nvariances in actual expenditure on a given budget line compared to the funds allocated for that purpose;\r\nexchange rate fluctuations affecting the amount of funding available to a project;\r\nthe burn rate of project funds rising or falling dramatically as a result of unforeseen changes in the operating environment, such as renewed volatility in an insecure, post-conflict environment, or a sudden onset natural disaster in a climate-affected region.\r\nThese are additional examples of financial information that may not be conveyed through the financial report, which instead can be described here.', NULL, NULL, NULL, '[]', '[]', NULL, NULL, '[]', '[]', NULL, NULL, '[]', '[]', NULL, NULL, '[]', '[]'),
(24, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[]', '[]', 'monthly report', '2025-10-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'woldiya_finance', '2025-10-01 21:55:39', '2025-10-01 21:55:39', '[]', '[]', '[]', NULL, 'In this section, summarize the objective that the project aims to achieve, the progress made in achieving/contributing to the realization of the outputs, outcomes as specified in the Results Matrix/Log frame, and the main activities that were implemented during the reporting period including key challenges encountered.', 'In this section, reflect on your operating environment and its impact on project implementation. You are encouraged to conduct a PESTLE analysis, which involves reflecting on the Political, Economic, Social, Technological, Legal, and Environmental considerations that impacted your context. This could include factors such as government policies, political stability, tax policies, trade restrictions, tariffs, inflation rates, interest rates, economic growth, exchange rates, conflict and economic stability, among others. For each item described, reflect on how it impacts the project and provide recommendations based on the analysis to mitigate risks and leverage opportunities. If none of the external factors have affected the environment in the reporting period, please leave this section blank.', 'In this question, provide narrative text focused mainly on describing details of activity implementation by project output and outcome (as exactly stated in your project results framework), including strategy employed in carrying out the activities and cross-cutting issues such as gender. Also mention activities that were planned but not carried out during the reporting period and why. Describe how the strategy used, and the accomplishment of the activities, is leading to progress or realization of project outputs/outcomes/objectives.', '[\"Finalized Project Proposal .docx\"]', '[\"uploads\\/documents\\/68dda35b00df4_1759355739.docx\"]', '4.1: Explain the challenges faced in this quarter and how they were addressed. (250 words max) *\r\n', '4.2: Highlight any mitigation measures taken. (250 words max) *', '[\"Safeguarding Manual (1).docx\"]', '[\"uploads\\/documents\\/68dda35b00faa_1759355739.docx\"]', '5.1: Please outline any good practices, lessons learnt, and recommendations from this period. (350 words max) *', 'This can include project-related publications and/or press articles. Think of changes to peoples\' lives, behaviour, organisational capacity, policy etc. It should be supported with photos and quotes where possible.', '[\"create beautiful image for my company N.A Ride Driving School with only english text (1).jpg\"]', '[\"uploads\\/images\\/68dda35b0132b_1759355739.jpg\"]', NULL, NULL, '[]', '[]'),
(25, 'Progress Report', NULL, 'Woldiya', '[]', '[]', '[]', '[]', 'Biannual Report', '2025-10-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'woldiya_finance', '2025-10-01 22:06:14', '2025-10-05 20:56:17', '[]', '[]', '[]', NULL, 'In this section, it is essential to reflect on the operating environment and its influence on project implementation. A comprehensive understanding of external factors helps ensure projects remain adaptive and resilient. One useful framework for this is the PESTLE analysis, which considers Political, Economic, Social, Technological, Legal, and Environmental elements. Politically, factors such as government stability, policies, and regulations can either facilitate or hinder project activities. For example, favorable tax policies or supportive government programs may accelerate progress,', 'Understanding the operating environment is crucial for effective project implementation, as external factors can significantly influence outcomes, timelines, and resource utilization. One of the most structured approaches to this reflection is conducting a PESTLE analysis, which examines Political, Economic, Social, Technological, Legal, and Environmental factors affecting the project context. Politically, government stability, regulatory policies, and political developments shape the operational landscape. Stable governance and supportive policies may facilitate smoother execution, whereas frequent policy shifts or political unrest can disrupt activities and increase risks. Economic factors, including inflation, interest rates, trade restrictions, exchange rates, and overall economic growth, directly affect project budgeting, procurement, and financial planning. Projects operating in volatile economic environments require adaptive strategies to manage costs and maintain financial sustainability. Social factors encompass demographic trends, cultural norms, community engagement, and social attitudes toward the project. Ensuring community buy-in and culturally sensitive approaches enhances participation and overall project effectiveness. Technological considerations, such as access to digital tools, internet connectivity, and infrastructure, influence the efficiency, monitoring, and reporting of project activities. Legal and regulatory factors, including labor laws, contract enforcement, and compliance with national and local regulations, ', 'A thorough understanding of the operating environment is a fundamental aspect of successful project implementation, as it provides insight into external factors that can significantly influence outcomes, timelines, and overall efficiency. One widely recognized framework for analyzing such influences is the PESTLE analysis, which examines the Political, Economic, Social, Technological, Legal, and Environmental factors that shape the context in which a project operates. Each of these dimensions plays a critical role in determining both the opportunities and challenges that a project may encounter, and reflecting on them allows teams to proactively mitigate risks while leveraging favorable conditions.\r\n\r\nPolitical factors are central to any project’s operational environment. Government stability, policy directions, regulatory changes, and the broader political climate can either facilitate or hinder project execution. For instance, a stable political environment with clear and consistent regulations enhances predictability, supports stakeholder engagement, and reduces the likelihood of disruptions. Conversely, political instability, frequent policy shifts, or bureaucratic delays can lead to uncertainty, slow decision-making, and unexpected operational challenges. Engaging with local authorities, monitoring policy developments, and maintaining strong relationships with government bodies are essential strategies for mitigating political risks.\r\n\r\nEconomic considerations also play a vital role in shaping project implementation. Factors such as inflation, interest rates, taxation, trade restrictions, exchange rates, and overall economic growth influence project costs, budgeting, and resource allocation. Economic volatility can lead to sudden increases in expenses or fluctuations in funding availability, necessitating flexible financial planning and contingency measures. Understanding the local economic context allows project teams to optimize procurement strategies, adjust timelines, and ensure that resources are allocated efficiently to maintain project continuity. Additionally, economic analysis helps identify opportunities for cost savings or partnerships that can strengthen project sustainability.', '[\"Final Budget.xlsx\"]', '[\"uploads\\/documents\\/68dda5d677185_1759356374.xlsx\"]', '4.1: Explain the challenges faced in this quarter and how they were addressed. (250 words max) *', '4.1: Explain the challenges faced in this quarter and how they were addressed. (250 words max) *', '[\"Copy of Baseline Assessment Report.pdf\"]', '[\"uploads\\/documents\\/68dda5d685efb_1759356374.pdf\"]', 'In this section, you will document the good practices, lessons learnt, and recommendations based on your project implementation experience. This information is crucial for continuous improvement and knowledge sharing.', '6.1: Share a change story or best practice narrative that was the highlight of the reporting period. (500 words max) *', '[\"create beautiful image for my company N.A Ride Driving School with only english text (1).jpg\",\"create beautiful image for my company N.A Ride Driving School.jpg\",\"create beautiful image for my company N.A Ride Driving School with only english text.jpg\"]', '[\"uploads\\/images\\/68dda5d6860d0_1759356374.jpg\",\"uploads\\/images\\/68dda5d686228_1759356374.jpg\",\"uploads\\/images\\/68dda5d686761_1759356374.jpg\"]', NULL, NULL, '[]', '[]'),
(26, 'Progress Report', NULL, 'Mekele', '[]', '[]', '[]', '[]', 'monthly report', '2025-10-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin', '2025-10-09 19:25:26', '2025-10-09 19:25:26', '[]', '[\"Final Budget.xlsx\"]', '[\"uploads\\/documents\\/68e80c26bb4b5_1760037926.xlsx\"]', 'fff', 'hhh', 'hgh', 'hg', '[\"Sample-Employee-Data.xlsx\"]', '[\"uploads\\/documents\\/68e80c26bace6_1760037926.xlsx\"]', 'hh', 'ggg g', '[\"Implementation Plan - MMI April 2025 (2).xlsx\"]', '[\"uploads\\/documents\\/68e80c26bb176_1760037926.xlsx\"]', 'gg gg', 'ggg gg', '[\"create beautiful image for my company N.A Ride Driving School with only english text.jpg\"]', '[\"uploads\\/images\\/68e80c26bb321_1760037926.jpg\"]', NULL, NULL, '[]', '[]'),
(27, 'Progress Report', NULL, 'Mekele', '[]', '[]', '[]', '[]', 'monthly report', '2025-10-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'admin', '2025-10-09 19:28:25', '2025-10-09 19:28:25', '[]', '[]', '[]', 'gg gg', 'hh', 'gg', 'gg', '[]', '[]', 'gg', 'gg', '[]', '[]', 'gg', 'gg', '[]', '[]', NULL, NULL, '[]', '[]');

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

CREATE TABLE `project_members` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `assigned_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `project_id` int(11) NOT NULL,
  `task_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `assigned_to` int(11) DEFAULT NULL,
  `status` enum('not_started','in_progress','completed','on_hold') NOT NULL DEFAULT 'not_started',
  `priority` enum('low','medium','high','urgent') NOT NULL DEFAULT 'medium',
  `due_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','finance_officer') NOT NULL DEFAULT 'finance_officer',
  `cluster_name` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `cluster_name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@gmail.com', '1234', 'admin', 'Mekele', 1, '2025-08-24 21:15:28', '2025-08-24 23:30:59'),
(12, 'woldiya_finance', 'finance@woldiya.com', '1234', 'finance_officer', 'Woldiya', 1, '2025-10-05 17:38:43', '2025-10-05 17:38:43'),
(13, 'test', 't@gmail.com', '1234', 'finance_officer', 'Test', 1, '2025-10-05 20:33:45', '2025-10-05 20:33:45'),
(14, 'n', 'n@gmail.com', '1234', 'finance_officer', 'NED', 1, '2025-10-12 18:16:48', '2025-10-12 18:16:48'),
(15, 'a', 'a@gmail.com', '1234', 'finance_officer', 'AFD', 1, '2025-10-12 18:18:02', '2025-10-12 18:18:02'),
(16, 'a', 'ad@gmail.com', '1234', 'finance_officer', 'Addis Ababa', 1, '2025-10-12 18:57:50', '2025-10-12 18:57:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bank_reconciliation_documents`
--
ALTER TABLE `bank_reconciliation_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `budget_data`
--
ALTER TABLE `budget_data`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `budget_ppreview`
--
ALTER TABLE `budget_ppreview`
  ADD PRIMARY KEY (`PreviewID`);

--
-- Indexes for table `budget_preview`
--
ALTER TABLE `budget_preview`
  ADD PRIMARY KEY (`PreviewID`),
  ADD KEY `budget_id` (`budget_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_year_category` (`year`,`category_name`),
  ADD KEY `idx_certificate_path` (`certificate_path`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `certificates_simple`
--
ALTER TABLE `certificates_simple`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_category_document` (`category`,`document_name`);

--
-- Indexes for table `clusters`
--
ALTER TABLE `clusters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cluster_name` (`cluster_name`);

--
-- Indexes for table `currency_rates`
--
ALTER TABLE `currency_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cluster_currency_pair` (`cluster_id`,`from_currency`,`to_currency`),
  ADD KEY `idx_cluster_active` (`cluster_id`,`is_active`),
  ADD KEY `idx_updated_by` (`updated_by`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `predefined_fields`
--
ALTER TABLE `predefined_fields`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_documents`
--
ALTER TABLE `project_documents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bank_reconciliation_documents`
--
ALTER TABLE `bank_reconciliation_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `budget_data`
--
ALTER TABLE `budget_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=417;

--
-- AUTO_INCREMENT for table `budget_preview`
--
ALTER TABLE `budget_preview`
  MODIFY `PreviewID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=231;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `certificates_simple`
--
ALTER TABLE `certificates_simple`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `checklist_items`
--
ALTER TABLE `checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=227;

--
-- AUTO_INCREMENT for table `clusters`
--
ALTER TABLE `clusters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `currency_rates`
--
ALTER TABLE `currency_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `predefined_fields`
--
ALTER TABLE `predefined_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_documents`
--
ALTER TABLE `project_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `project_members`
--
ALTER TABLE `project_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

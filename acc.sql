-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               8.4.3 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for acc
CREATE DATABASE IF NOT EXISTS `acc` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `acc`;

-- Dumping structure for table acc.accounts
CREATE TABLE IF NOT EXISTS `accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `account_code` varchar(20) NOT NULL,
  `account_name` varchar(255) NOT NULL,
  `account_type` enum('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
  `balance` decimal(12,2) DEFAULT '0.00',
  `description` text,
  `company_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_code_company` (`account_code`,`company_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `accounts_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1712 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table acc.accounts: ~0 rows (approximately)

-- Dumping structure for table acc.archive_expenses
CREATE TABLE IF NOT EXISTS `archive_expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `original_id` int NOT NULL,
  `date` date NOT NULL,
  `receipt_no` varchar(50) DEFAULT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `supplier` varchar(255) DEFAULT NULL,
  `supplier_tin` varchar(50) DEFAULT NULL,
  `explanation` text,
  `category` varchar(100) NOT NULL,
  `sub_category` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `notes` text,
  `company_id` int NOT NULL,
  `archived_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_original_id` (`original_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1754 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table acc.archive_expenses: ~0 rows (approximately)

-- Dumping structure for table acc.archive_income
CREATE TABLE IF NOT EXISTS `archive_income` (
  `id` int NOT NULL AUTO_INCREMENT,
  `original_id` int NOT NULL,
  `date` date NOT NULL,
  `reference_no` varchar(50) DEFAULT NULL,
  `source` varchar(255) NOT NULL,
  `explanation` text,
  `category` varchar(100) NOT NULL,
  `sub_category` varchar(100) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` varchar(50) NOT NULL,
  `notes` text,
  `company_id` int NOT NULL,
  `archived_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_original_id` (`original_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3624 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table acc.archive_income: ~0 rows (approximately)

-- Dumping structure for table acc.companies
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `theme_color` varchar(20) DEFAULT '#38B2AC',
  `dark_mode` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table acc.companies: ~4 rows (approximately)
REPLACE INTO `companies` (`id`, `name`, `description`, `created_at`, `theme_color`, `dark_mode`) VALUES
	(1, 'Church', 'Primary church organization', '2025-04-17 13:58:26', '#38B2AC', 0),
	(9, 'JEHU', '', '2025-04-25 05:14:32', '#38B2AC', 0),
	(11, 'Testing', '', '2025-04-28 05:45:35', '#38B2AC', 0),
	(12, 'test 2', '', '2025-05-06 05:52:23', '#38B2AC', 0);

-- Dumping structure for table acc.expenses
CREATE TABLE IF NOT EXISTS `expenses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `receipt_no` varchar(100) DEFAULT NULL,
  `vendor_name` varchar(255) NOT NULL,
  `explanation` text,
  `category` varchar(100) NOT NULL,
  `sub_category` varchar(100) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('Cash','Check','Bank Transfer','Credit Card') DEFAULT 'Cash',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `company_id` int NOT NULL DEFAULT '1',
  `supplier` varchar(255) DEFAULT NULL,
  `supplier_tin` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10645 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table acc.expenses: ~0 rows (approximately)

-- Dumping structure for table acc.income
CREATE TABLE IF NOT EXISTS `income` (
  `id` int NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL,
  `donor_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `invoice_no` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT 'Revenue',
  `sub_category` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'Tithes',
  `amount` decimal(12,2) NOT NULL,
  `payment_method` enum('Cash','Check','Bank Transfer','Credit Card','Online') DEFAULT 'Cash',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `company_id` int NOT NULL DEFAULT '1',
  `payor` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `income_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27456 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table acc.income: ~0 rows (approximately)

-- Dumping structure for table acc.journal_entries
CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_id` int NOT NULL,
  `account_code` varchar(20) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `entry_type` enum('debit','credit') NOT NULL,
  `company_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `company_id` (`company_id`),
  KEY `journal_entries_ibfk_2` (`account_code`,`company_id`),
  CONSTRAINT `journal_entries_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`),
  CONSTRAINT `journal_entries_ibfk_2` FOREIGN KEY (`account_code`, `company_id`) REFERENCES `accounts` (`account_code`, `company_id`),
  CONSTRAINT `journal_entries_ibfk_3` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=74154 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table acc.journal_entries: ~0 rows (approximately)

-- Dumping structure for table acc.transactions
CREATE TABLE IF NOT EXISTS `transactions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `transaction_date` date NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `company_id` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37091 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table acc.transactions: ~0 rows (approximately)

-- Dumping structure for table acc.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `is_admin` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Dumping data for table acc.users: ~1 rows (approximately)
REPLACE INTO `users` (`id`, `username`, `password_hash`, `email`, `full_name`, `is_admin`, `created_at`) VALUES
	(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@church.org', 'System Administrator', 1, '2025-04-18 10:56:00');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

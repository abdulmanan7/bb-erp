-- BB Builders ERP — MySQL schema
-- Import into your database via phpMyAdmin (select your DB → Import → this file)

CREATE TABLE IF NOT EXISTS `settings` (
  `id` tinyint unsigned NOT NULL PRIMARY KEY,
  `company_name` varchar(150) NOT NULL DEFAULT 'BB Builders',
  `tagline` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `phone` varchar(50) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `logo` mediumtext,
  `primary_color` varchar(10) NOT NULL DEFAULT '#0b2e4f',
  `secondary_color` varchar(10) NOT NULL DEFAULT '#e0952e',
  `currency` varchar(10) NOT NULL DEFAULT 'Rs',
  `tax_rate` decimal(6,2) NOT NULL DEFAULT 0,
  `invoice_footer` varchar(255) NOT NULL DEFAULT 'Thank you for your business!'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `settings` (`id`, `company_name`, `tagline`, `address`)
VALUES (1, 'BB Builders', 'Construction & Development', 'Pakistan')
ON DUPLICATE KEY UPDATE `id` = `id`;

CREATE TABLE IF NOT EXISTS `users` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `employee_id` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(150) NOT NULL,
  `username` varchar(60) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `type` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin: BBAccounts / admin2026
-- NOTE: stored plain only for first login; auto-hashed on first successful login.
INSERT INTO `users` (`id`, `employee_id`, `name`, `username`, `password`, `type`)
VALUES ('admin-main', '', 'Admin', 'BBAccounts', 'admin2026', 'admin')
ON DUPLICATE KEY UPDATE `username` = `username`;

CREATE TABLE IF NOT EXISTS `heads` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `name` varchar(150) NOT NULL,
  `type` enum('Expense','Income') NOT NULL DEFAULT 'Expense',
  `category` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employees` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `name` varchar(150) NOT NULL,
  `phone` varchar(50) NOT NULL DEFAULT '',
  `designation` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `employee_heads` (
  `employee_id` varchar(32) NOT NULL,
  `head_id` varchar(32) NOT NULL,
  PRIMARY KEY (`employee_id`, `head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vouchers` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `no` varchar(30) NOT NULL,
  `type` enum('Payment','Receipt') NOT NULL,
  `v_date` date NOT NULL,
  `head_id` varchar(32) NOT NULL DEFAULT '',
  `amount` decimal(14,2) NOT NULL DEFAULT 0,
  `party` varchar(150) NOT NULL DEFAULT '',
  `description` text,
  `attachment` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  KEY `idx_vdate` (`v_date`),
  KEY `idx_head` (`head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoices` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `no` varchar(30) NOT NULL,
  `client` varchar(150) NOT NULL,
  `address` varchar(255) NOT NULL DEFAULT '',
  `inv_date` date NOT NULL,
  `notes` text,
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `invoice_id` varchar(32) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `qty` decimal(12,2) NOT NULL DEFAULT 0,
  `rate` decimal(14,2) NOT NULL DEFAULT 0,
  KEY `idx_invoice` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `salary_slips` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `employee_id` varchar(32) NOT NULL DEFAULT '',
  `employee_name` varchar(150) NOT NULL,
  `designation` varchar(100) NOT NULL DEFAULT '',
  `phone` varchar(50) NOT NULL DEFAULT '',
  `sal_month` tinyint NOT NULL DEFAULT 1,
  `sal_year` smallint NOT NULL DEFAULT 2025,
  `basic` decimal(14,2) NOT NULL DEFAULT 0,
  `allowance` decimal(14,2) NOT NULL DEFAULT 0,
  `deduction` decimal(14,2) NOT NULL DEFAULT 0,
  `bonus` decimal(14,2) NOT NULL DEFAULT 0,
  `total` decimal(14,2) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

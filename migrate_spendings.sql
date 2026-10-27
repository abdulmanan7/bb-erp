-- BB Builders ERP — staff spending ledger migration
-- Run once on an existing database (phpMyAdmin → Import → this file)
-- Additive only: creates the spendings table. Nothing else is changed.

CREATE TABLE IF NOT EXISTS `spendings` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `kind` enum('advance','spend') NOT NULL,
  `employee_id` varchar(32) NOT NULL,
  `project_id` varchar(32) NOT NULL,
  `head_id` varchar(32) NOT NULL DEFAULT '',
  `sub_head_id` varchar(32) NOT NULL DEFAULT '',
  `spend_date` date NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0,
  `party` varchar(150) NOT NULL DEFAULT '',
  `description` text,
  `attachment` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  KEY `idx_spend_employee` (`employee_id`),
  KEY `idx_spend_project` (`project_id`),
  KEY `idx_spend_date` (`spend_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

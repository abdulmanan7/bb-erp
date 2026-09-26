-- BB Builders ERP — sub-heads migration
-- Run once on an existing database (phpMyAdmin → Import → this file)

CREATE TABLE IF NOT EXISTS `sub_heads` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `head_id` varchar(32) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  KEY `idx_head` (`head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `vouchers` ADD COLUMN `sub_head_id` varchar(32) NOT NULL DEFAULT '' AFTER `head_id`;

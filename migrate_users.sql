-- Migration: move credentials into a dedicated `users` table
-- Run once on an existing bb_erp database (phpMyAdmin → SQL tab)

CREATE TABLE IF NOT EXISTS `users` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `employee_id` varchar(32) NOT NULL DEFAULT '',
  `name` varchar(150) NOT NULL,
  `username` varchar(60) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `type` enum('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carry over the admin account from settings (hash kept as-is)
INSERT INTO `users` (`id`, `employee_id`, `name`, `username`, `password`, `type`, `created_at`)
SELECT 'admin-main', '', 'Admin', admin_user, admin_pass, 'admin', NOW()
FROM `settings` WHERE `id` = 1
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`);

-- Carry over employee logins as staff users
INSERT INTO `users` (`id`, `employee_id`, `name`, `username`, `password`, `type`, `created_at`)
SELECT CONCAT('u-', `id`), `id`, `name`, `username`, `password`, 'staff', NOW()
FROM `employees` WHERE `login_enabled` = 1 AND `username` <> ''
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`);

ALTER TABLE `employees` DROP COLUMN `login_enabled`, DROP COLUMN `username`, DROP COLUMN `password`;
ALTER TABLE `settings` DROP COLUMN `admin_user`, DROP COLUMN `admin_pass`;

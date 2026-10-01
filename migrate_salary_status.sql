-- BB Builders ERP — salary slip status migration
-- Run once on an existing database (phpMyAdmin → Import → this file)

ALTER TABLE `salary_slips`
  ADD COLUMN `status` enum('pending','paid') NOT NULL DEFAULT 'pending' AFTER `total`,
  ADD COLUMN `voucher_id` varchar(32) NOT NULL DEFAULT '' AFTER `status`;

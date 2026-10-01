-- BB Builders ERP — voucher payment status migration
-- Run once on an existing database (phpMyAdmin → Import → this file)

ALTER TABLE `vouchers`
  ADD COLUMN `total` decimal(14,2) NOT NULL DEFAULT 0 AFTER `amount`,
  ADD COLUMN `paid` decimal(14,2) NOT NULL DEFAULT 0 AFTER `total`,
  ADD COLUMN `status` enum('pending','partial','paid') NOT NULL DEFAULT 'paid' AFTER `paid`;

UPDATE `vouchers` SET `total` = `amount`, `paid` = `amount`, `status` = 'paid';

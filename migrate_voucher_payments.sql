-- BB Builders ERP — voucher payment ledger migration
-- Run once on an existing database (phpMyAdmin → Import → this file)
-- Additive only: creates voucher_payments and backfills one entry per
-- voucher that already has a paid amount. No data is altered or removed.

CREATE TABLE IF NOT EXISTS `voucher_payments` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `voucher_id` varchar(32) NOT NULL,
  `amount` decimal(14,2) NOT NULL DEFAULT 0,
  `pay_date` date NOT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  KEY `idx_voucher` (`voucher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `voucher_payments` (`id`, `voucher_id`, `amount`, `pay_date`, `note`, `created_by`, `created_at`)
SELECT CONCAT('bp-', `id`), `id`, `paid`, `v_date`, 'Recorded before payment tracking', `created_by`, `created_at`
FROM `vouchers`
WHERE `paid` > 0;

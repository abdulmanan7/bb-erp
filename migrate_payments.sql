-- BB Builders ERP — payments section migration
-- Run once on an existing database (phpMyAdmin → Import → this file).
-- Partial/pending vouchers become Payment records. Each installment of a
-- partial voucher becomes its own voucher entry linked to that Payment.
-- Drops vouchers.status and vouchers.total.

CREATE TABLE IF NOT EXISTS `payments` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `type` enum('Payment','Receipt') NOT NULL,
  `p_date` date NOT NULL,
  `head_id` varchar(32) NOT NULL DEFAULT '',
  `sub_head_id` varchar(32) NOT NULL DEFAULT '',
  `total` decimal(14,2) NOT NULL DEFAULT 0,
  `description` text,
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  KEY `idx_pdate` (`p_date`),
  KEY `idx_head` (`head_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `vouchers`
  ADD COLUMN `payment_id` varchar(32) NOT NULL DEFAULT '' AFTER `sub_head_id`,
  ADD KEY `idx_payment` (`payment_id`);

INSERT INTO `voucher_payments` (`id`, `voucher_id`, `amount`, `pay_date`, `note`, `created_by`, `created_at`)
SELECT CONCAT('bp-', `id`), `id`, `paid`, `v_date`, 'Recorded before payment tracking', `created_by`, `created_at`
FROM `vouchers`
WHERE `paid` > 0 AND `id` NOT IN (SELECT `voucher_id` FROM `voucher_payments`);

CREATE TEMPORARY TABLE `tmp_split` AS
SELECT v.`id`, COUNT(l.`id`) AS `n`
FROM `vouchers` v
LEFT JOIN `voucher_payments` l ON l.`voucher_id` = v.`id`
GROUP BY v.`id`, v.`status`
HAVING v.`status` <> 'paid' OR COUNT(l.`id`) > 1;

INSERT INTO `payments` (`id`, `type`, `p_date`, `head_id`, `sub_head_id`, `total`, `description`, `created_by`, `created_at`)
SELECT CONCAT('pm-', v.`id`), v.`type`, v.`v_date`, v.`head_id`, v.`sub_head_id`, v.`total`, v.`description`, v.`created_by`, v.`created_at`
FROM `vouchers` v
JOIN `tmp_split` s ON s.`id` = v.`id`;

DELETE FROM `vouchers` WHERE `id` IN (SELECT `id` FROM `tmp_split` WHERE `n` = 0);

INSERT INTO `vouchers` (`id`, `no`, `type`, `v_date`, `head_id`, `sub_head_id`, `amount`, `paid`, `party`, `description`, `attachment`, `payment_id`, `created_by`, `created_at`)
SELECT r.`id`, CONCAT(v.`no`, '-', r.`rn`), v.`type`, r.`pay_date`, v.`head_id`, v.`sub_head_id`, r.`amount`, r.`amount`, v.`party`, v.`description`, '', CONCAT('pm-', v.`id`), v.`created_by`, r.`created_at`
FROM (
  SELECT l.`id`, l.`voucher_id`, l.`amount`, l.`pay_date`, l.`created_at`,
         ROW_NUMBER() OVER (PARTITION BY l.`voucher_id` ORDER BY l.`pay_date`, l.`id`) AS `rn`
  FROM `voucher_payments` l
  JOIN `tmp_split` s ON s.`id` = l.`voucher_id`
) r
JOIN `vouchers` v ON v.`id` = r.`voucher_id`
WHERE r.`rn` > 1;

UPDATE `vouchers` v
JOIN `voucher_payments` l ON l.`voucher_id` = v.`id`
JOIN `tmp_split` s ON s.`id` = v.`id`
SET v.`amount` = l.`amount`, v.`paid` = l.`amount`, v.`v_date` = l.`pay_date`, v.`payment_id` = CONCAT('pm-', v.`id`)
WHERE l.`id` = (
  SELECT x.`id` FROM `voucher_payments` x
  WHERE x.`voucher_id` = v.`id`
  ORDER BY x.`pay_date`, x.`id`
  LIMIT 1
);

UPDATE `vouchers` SET `paid` = `amount`;

ALTER TABLE `vouchers`
  DROP COLUMN `total`,
  DROP COLUMN `status`;

DROP TEMPORARY TABLE IF EXISTS `tmp_split`;

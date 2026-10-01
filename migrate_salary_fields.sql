-- BB Builders ERP — employee salary defaults migration
-- Run once on an existing database (phpMyAdmin → Import → this file)

ALTER TABLE `employees`
  ADD COLUMN `basic_salary` decimal(14,2) NOT NULL DEFAULT 0 AFTER `designation`,
  ADD COLUMN `allowance` decimal(14,2) NOT NULL DEFAULT 0 AFTER `basic_salary`,
  ADD COLUMN `deduction` decimal(14,2) NOT NULL DEFAULT 0 AFTER `allowance`,
  ADD COLUMN `bonus` decimal(14,2) NOT NULL DEFAULT 0 AFTER `deduction`;

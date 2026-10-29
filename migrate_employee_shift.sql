-- Migration: per-employee shift length (hours)
-- Run once on an existing bb_erp database (phpMyAdmin → SQL tab)

ALTER TABLE `employees` ADD COLUMN `shift_hours` decimal(4,2) NOT NULL DEFAULT 8 AFTER `bonus`;

-- Migration: per-project start time and late check-ins
-- Run once on an existing bb_erp database (phpMyAdmin → SQL tab)

ALTER TABLE `projects`
  ADD COLUMN `start_time` time DEFAULT NULL AFTER `timezone`;

ALTER TABLE `attendance_sessions`
  ADD COLUMN `late_minutes` smallint unsigned DEFAULT NULL AFTER `check_in_device`;

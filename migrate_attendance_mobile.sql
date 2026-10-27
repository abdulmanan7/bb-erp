-- Migration: projects, attendance (GPS), mobile sessions, leave, announcements, notifications
-- Run once on an existing bb_erp database (phpMyAdmin → SQL tab)

CREATE TABLE IF NOT EXISTS `projects` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `name` varchar(150) NOT NULL,
  `description` text,
  `address` varchar(255) NOT NULL DEFAULT '',
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `radius_m` smallint unsigned NOT NULL DEFAULT 100,
  `timezone` varchar(50) NOT NULL DEFAULT 'Asia/Karachi',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  KEY `idx_project_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `project_assignments` (
  `id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `project_id` varchar(32) NOT NULL,
  `employee_id` varchar(32) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  UNIQUE KEY `uniq_project_employee` (`project_id`, `employee_id`),
  KEY `idx_assignment_employee` (`employee_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance_sessions` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `employee_id` varchar(32) NOT NULL,
  `project_id` varchar(32) NOT NULL,
  `attendance_date` date NOT NULL,
  `status` enum('active','closed') NOT NULL DEFAULT 'active',
  `active_employee_id` varchar(32) GENERATED ALWAYS AS (IF(`status` = 'active', `employee_id`, NULL)) STORED,
  `check_in_at` datetime NOT NULL,
  `check_in_lat` decimal(10,7) NOT NULL,
  `check_in_lng` decimal(10,7) NOT NULL,
  `check_in_accuracy` decimal(8,2) NOT NULL DEFAULT 0,
  `check_in_distance_m` decimal(10,2) NOT NULL DEFAULT 0,
  `check_in_inside` tinyint(1) NOT NULL DEFAULT 1,
  `check_in_event_id` varchar(64) DEFAULT NULL,
  `check_in_device` varchar(150) NOT NULL DEFAULT '',
  `check_out_at` datetime DEFAULT NULL,
  `check_out_lat` decimal(10,7) DEFAULT NULL,
  `check_out_lng` decimal(10,7) DEFAULT NULL,
  `check_out_accuracy` decimal(8,2) DEFAULT NULL,
  `check_out_distance_m` decimal(10,2) DEFAULT NULL,
  `check_out_inside` tinyint(1) DEFAULT NULL,
  `check_out_event_id` varchar(64) DEFAULT NULL,
  `duration_seconds` int unsigned NOT NULL DEFAULT 0,
  `exceptions` varchar(255) NOT NULL DEFAULT '',
  `last_location_at` datetime DEFAULT NULL,
  `last_lat` decimal(10,7) DEFAULT NULL,
  `last_lng` decimal(10,7) DEFAULT NULL,
  `last_distance_m` decimal(10,2) DEFAULT NULL,
  `last_inside` tinyint(1) DEFAULT NULL,
  `last_delayed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  UNIQUE KEY `uniq_active_employee` (`active_employee_id`),
  UNIQUE KEY `uniq_checkin_event` (`employee_id`, `check_in_event_id`),
  KEY `idx_att_employee_date` (`employee_id`, `attendance_date`),
  KEY `idx_att_project_date` (`project_id`, `attendance_date`),
  KEY `idx_att_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `attendance_location_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `event_id` varchar(64) NOT NULL,
  `session_id` varchar(32) NOT NULL,
  `employee_id` varchar(32) NOT NULL,
  `project_id` varchar(32) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `accuracy` decimal(8,2) NOT NULL DEFAULT 0,
  `distance_m` decimal(10,2) NOT NULL DEFAULT 0,
  `inside_boundary` tinyint(1) NOT NULL DEFAULT 1,
  `is_mocked` tinyint(1) NOT NULL DEFAULT 0,
  `captured_at` datetime NOT NULL,
  `received_at` datetime NOT NULL,
  `is_delayed` tinyint(1) NOT NULL DEFAULT 0,
  UNIQUE KEY `uniq_location_event` (`employee_id`, `event_id`),
  KEY `idx_loc_session_time` (`session_id`, `captured_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `mobile_sessions` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `user_id` varchar(32) NOT NULL,
  `employee_id` varchar(32) NOT NULL,
  `device_id` varchar(100) NOT NULL DEFAULT '',
  `device_name` varchar(150) NOT NULL DEFAULT '',
  `platform` enum('android','ios') NOT NULL DEFAULT 'android',
  `access_token_hash` char(64) NOT NULL,
  `access_expires_at` datetime NOT NULL,
  `refresh_token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  UNIQUE KEY `uniq_access_token` (`access_token_hash`),
  KEY `idx_mobile_user` (`user_id`, `revoked_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `device_push_tokens` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `user_id` varchar(32) NOT NULL,
  `employee_id` varchar(32) NOT NULL,
  `platform` enum('android','ios') NOT NULL,
  `device_id` varchar(100) NOT NULL DEFAULT '',
  `device_info` varchar(150) NOT NULL DEFAULT '',
  `token` varchar(255) NOT NULL UNIQUE,
  `last_seen_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  KEY `idx_push_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `name` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `leave_types` (`id`, `name`, `created_at`) VALUES
  ('lt-annual', 'Annual Leave', NOW()),
  ('lt-sick', 'Sick Leave', NOW()),
  ('lt-casual', 'Casual Leave', NOW()),
  ('lt-unpaid', 'Unpaid Leave', NOW())
ON DUPLICATE KEY UPDATE `id` = `id`;

CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `employee_id` varchar(32) NOT NULL,
  `leave_type_id` varchar(32) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `days` smallint unsigned NOT NULL DEFAULT 1,
  `reason` varchar(500) NOT NULL DEFAULT '',
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `admin_remarks` varchar(255) NOT NULL DEFAULT '',
  `reviewed_by` varchar(100) NOT NULL DEFAULT '',
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  KEY `idx_leave_employee` (`employee_id`, `status`),
  KEY `idx_leave_status_dates` (`status`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `title` varchar(200) NOT NULL,
  `content` text,
  `audience` enum('all','selected') NOT NULL DEFAULT 'all',
  `status` enum('draft','published') NOT NULL DEFAULT 'draft',
  `published_at` datetime DEFAULT NULL,
  `created_by` varchar(100) NOT NULL DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  KEY `idx_announcement_status` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `announcement_targets` (
  `announcement_id` varchar(32) NOT NULL,
  `employee_id` varchar(32) NOT NULL,
  PRIMARY KEY (`announcement_id`, `employee_id`),
  KEY `idx_target_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `announcement_reads` (
  `announcement_id` varchar(32) NOT NULL,
  `employee_id` varchar(32) NOT NULL,
  `read_at` datetime NOT NULL,
  PRIMARY KEY (`announcement_id`, `employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` varchar(32) NOT NULL PRIMARY KEY,
  `employee_id` varchar(32) NOT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'general',
  `title` varchar(200) NOT NULL,
  `body` text,
  `reference_type` varchar(30) NOT NULL DEFAULT '',
  `reference_id` varchar(32) NOT NULL DEFAULT '',
  `dedupe_key` varchar(120) DEFAULT NULL,
  `push_status` enum('pending','sent','failed','skipped') NOT NULL DEFAULT 'pending',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  UNIQUE KEY `uniq_notification_dedupe` (`dedupe_key`),
  KEY `idx_notification_employee` (`employee_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

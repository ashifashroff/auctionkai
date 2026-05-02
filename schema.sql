-- ─────────────────────────────────────────────────────────────────
-- AuctionKai — MySQL Schema (Multi-Auction)
-- Run this entire file once in phpMyAdmin → SQL tab
-- ─────────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS `auctionkai`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `auctionkai`;

SET FOREIGN_KEY_CHECKS=0;

-- ── Users (login system — MUST be first due to foreign keys) ────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `email`      VARCHAR(200) DEFAULT '',
  `role`            VARCHAR(20) NOT NULL DEFAULT 'user',
  `status`          ENUM('active','suspended','restricted') NOT NULL DEFAULT 'active',
  `suspended_until` DATETIME DEFAULT NULL,
  `suspend_reason`  VARCHAR(255) DEFAULT NULL,
  `disabled`        TINYINT(1) DEFAULT 0,
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Auctions (multiple auctions) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `auction` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`         INT UNSIGNED NOT NULL,
  `name`            VARCHAR(200) NOT NULL,
  `date`            DATE         NOT NULL,
  `commission_fee` DECIMAL(12,0) NOT NULL DEFAULT 3300 COMMENT 'Commission fee per member',
  `expires_at`      DATE         NOT NULL COMMENT 'Auto-delete sold vehicles + auction after this date',
  `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Members (sellers — global, shared across auctions) ───────────
CREATE TABLE IF NOT EXISTS `members` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`     INT UNSIGNED NOT NULL,
  `name`        VARCHAR(200)  NOT NULL,
  `phone`       VARCHAR(50)   DEFAULT '',
  `email`       VARCHAR(200)  DEFAULT '',
  `created_at`  TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Vehicles ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `auction_id`  INT UNSIGNED NOT NULL,
  `member_id`   INT UNSIGNED NOT NULL,
  `make`        VARCHAR(100) NOT NULL,
  `model`       VARCHAR(100) DEFAULT '',
  `lot`         VARCHAR(50)  DEFAULT '',
  `sold_price`  DECIMAL(12,0) DEFAULT 0,
  `recycle_fee` DECIMAL(12,0) DEFAULT 0,
  `listing_fee` DECIMAL(12,0) DEFAULT 0,
  `sold_fee`    DECIMAL(12,0) DEFAULT 0,
  `nagare_fee`  DECIMAL(12,0) DEFAULT 0,
  `other_fee`   DECIMAL(12,0) DEFAULT 0,
  `sold`        TINYINT(1)   DEFAULT 1,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`auction_id`) REFERENCES `auction`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- Indexes
-- ─────────────────────────────────────────────────────────────────
CREATE INDEX idx_vehicles_auction_id ON vehicles(auction_id);
CREATE INDEX idx_vehicles_sold ON vehicles(sold);
CREATE INDEX idx_vehicles_member_id ON vehicles(member_id);
CREATE INDEX idx_auction_user_id ON auction(user_id);
CREATE INDEX idx_members_user_id ON members(user_id);

-- ── Password Resets ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `password_resets` (
  `id`         INT AUTO_INCREMENT PRIMARY KEY,
  `email`      VARCHAR(200) NOT NULL,
  `token`      VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- Seed Data
-- ─────────────────────────────────────────────────────────────────

-- Default admin user (username: admin, password: password)
INSERT INTO `users` (`username`, `password`, `name`, `email`, `role`) VALUES
  ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@auctionkai.com', 'admin');

-- Demo user (username: demo, password: password)
INSERT INTO `users` (`username`, `password`, `name`, `email`, `role`) VALUES
  ('demo', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Demo User', 'demo@example.com', 'user');

-- ─────────────────────────────────────────────────────────────────
-- Seed: Multiple Japanese auctions
-- ─────────────────────────────────────────────────────────────────
INSERT INTO `auction` (`user_id`, `name`, `date`, `expires_at`) VALUES
  (1, 'Nagoya Auto Auction',       CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
  (1, 'Tokyo Bay Auto Auction',    CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
  (1, 'Osaka JAA Auction',         CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
  (1, 'Yokohama Auto Auction',     CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
  (1, 'Fukuoka Auto Auction',      CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY)),
  (1, 'Sapporo Auto Auction',      CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY));


-- Sample members (global, user_id=1)
INSERT INTO `members` (`user_id`, `name`, `phone`, `email`) VALUES
  (1, 'Ahmad Hassan',        '090-1234-5678', 'ahmad@example.com'),
  (1, 'Mohammed Al-Rashid',  '080-9876-5432', 'm.rashid@example.com'),
  (1, 'Chen Wei',            '070-5555-0001', 'cwei@example.com'),
  (1, 'Tanaka Yuki',         '090-2222-3333', 'tanaka@example.com'),
  (1, 'Sato Kenji',          '080-4444-5555', 'sato@example.com');

-- Fee items per member (global members 1-5)
-- Sample vehicles (Nagoya & Tokyo auctions, members are global)
INSERT INTO `vehicles` (`auction_id`, `member_id`, `make`, `model`, `lot`, `sold_price`, `recycle_fee`, `listing_fee`, `sold_fee`, `nagare_fee`, `other_fee`, `sold`) VALUES
  (1, 1, 'Toyota',     'Prius',     'A-001',  850000, 15000, 3000, 25500, 8000, 0, 1),
  (1, 1, 'Honda',      'Fit',       'A-002',  420000, 12000, 3000, 12600, 8000, 0, 1),
  (1, 2, 'Nissan',     'Note',      'B-001',  680000, 13000, 3000, 20400, 8000, 0, 1),
  (1, 2, 'Mazda',      'CX-5',      'B-002', 1250000, 18000, 3000, 37500, 8000, 0, 1),
  (1, 3, 'Subaru',     'Forester',  'C-001',  920000, 16000, 3000, 27600, 8000, 0, 1),
  (1, 3, 'Mitsubishi', 'Outlander', 'C-002',       0,     0,     0,     0,    0, 0, 0),
  (2, 4, 'Honda',   'Civic',    'T-001',  780000, 14000, 3500, 27300, 8000, 0, 1),
  (2, 4, 'Toyota',  'Corolla',  'T-002',  550000, 12000, 3500, 19250, 8000, 0, 1),
  (2, 5, 'Lexus',   'IS 300',   'T-003', 1800000, 20000, 3500, 63000, 8000, 0, 1);

SET FOREIGN_KEY_CHECKS=1;

-- ── Migration: Add disabled column (for existing installs) ──────────
ALTER TABLE users ADD COLUMN IF NOT EXISTS disabled TINYINT(1) DEFAULT 0;

-- ── Settings (email/SMTP config managed via admin panel) ──────────
CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `key` VARCHAR(100) NOT NULL UNIQUE,
  value TEXT DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (`key`, value) VALUES
  ('mail_enabled', '0'),
  ('mail_provider', 'smtp'),
  ('mail_host', ''),
  ('mail_port', '587'),
  ('mail_username', ''),
  ('mail_password', ''),
  ('mail_from_email', ''),
  ('mail_from_name', 'AuctionKai Settlement System'),
  ('mail_encryption', 'tls'),
  ('session_timeout_enabled', '1'),
  ('session_timeout_minutes', '30'),
  ('session_timeout_warn_minutes', '2'),
  ('maintenance_mode', '0'),
  ('maintenance_title', 'System Maintenance'),
  ('maintenance_message', 'AuctionKai is currently undergoing scheduled maintenance. We will be back shortly. Thank you for your patience.'),
  ('maintenance_eta', ''),
  ('brand_name', 'AuctionKai'),
  ('brand_tagline', 'Settlement Management System'),
  ('brand_owner', 'Mirai Global Solutions'),
  ('brand_email', ''),
  ('brand_phone', ''),
  ('brand_address', ''),
  ('brand_logo_url', ''),
  ('brand_accent_color', '#D4A84B'),
  ('brand_footer_text', 'Designed & Developed by Mirai Global Solutions'),
  ('backup_enabled', '0'),
  ('backup_frequency', 'daily'),
  ('backup_retention_days', '30'),
  ('backup_compress', '1'),
  ('backup_last_run', ''),
  ('backup_next_run', '')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Migration for existing installs
ALTER TABLE settings ADD COLUMN IF NOT EXISTS `key` VARCHAR(100) NOT NULL UNIQUE;

-- ── Performance Indexes ──────────────────────

-- vehicles table — most queried columns
ALTER TABLE vehicles
  ADD INDEX IF NOT EXISTS idx_vehicles_auction_id (`auction_id`),
  ADD INDEX IF NOT EXISTS idx_vehicles_member_id (`member_id`),
  ADD INDEX IF NOT EXISTS idx_vehicles_sold (`sold`),
  ADD INDEX IF NOT EXISTS idx_vehicles_lot (`lot`),
  ADD INDEX IF NOT EXISTS idx_vehicles_auction_sold (`auction_id`, `sold`);

-- auction table
ALTER TABLE auction
  ADD INDEX IF NOT EXISTS idx_auction_user_id (`user_id`),
  ADD INDEX IF NOT EXISTS idx_auction_expires_at (`expires_at`),
  ADD INDEX IF NOT EXISTS idx_auction_user_date (`user_id`, `date`);

-- members table
ALTER TABLE members
  ADD INDEX IF NOT EXISTS idx_members_user_id (`user_id`);

-- users table
ALTER TABLE users
  ADD INDEX IF NOT EXISTS idx_users_username (`username`),
  ADD INDEX IF NOT EXISTS idx_users_email (`email`),
  ADD INDEX IF NOT EXISTS idx_users_role (`role`);

-- password_resets table (if exists)
ALTER TABLE password_resets
  ADD INDEX IF NOT EXISTS idx_resets_email (`email`),
  ADD INDEX IF NOT EXISTS idx_resets_token (`token`);

-- settings table
ALTER TABLE settings
  ADD INDEX IF NOT EXISTS idx_settings_key (`key`);

-- ── For existing installs — run these manually ──
-- ── in phpMyAdmin SQL tab to add indexes ─────

CREATE INDEX IF NOT EXISTS idx_vehicles_auction_id ON vehicles(auction_id);
CREATE INDEX IF NOT EXISTS idx_vehicles_member_id ON vehicles(member_id);
CREATE INDEX IF NOT EXISTS idx_vehicles_sold ON vehicles(sold);
CREATE INDEX IF NOT EXISTS idx_vehicles_lot ON vehicles(lot);
CREATE INDEX IF NOT EXISTS idx_vehicles_auction_sold ON vehicles(auction_id, sold);
CREATE INDEX IF NOT EXISTS idx_auction_user_id ON auction(user_id);
CREATE INDEX IF NOT EXISTS idx_auction_expires_at ON auction(expires_at);
CREATE INDEX IF NOT EXISTS idx_auction_user_date ON auction(user_id, date);
CREATE INDEX IF NOT EXISTS idx_members_user_id ON members(user_id);
CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);

-- ── Activity Log Table ───────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) DEFAULT NULL,
  entity_id INT UNSIGNED DEFAULT NULL,
  description TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_log_user_id (`user_id`),
  INDEX idx_log_action (`action`),
  INDEX idx_log_created (`created_at`),
  INDEX idx_log_entity (`entity_type`, `entity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── For existing installs ─────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50) DEFAULT NULL,
  entity_id INT UNSIGNED DEFAULT NULL,
  description TEXT DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Login History ────────────────────────────
CREATE TABLE IF NOT EXISTS login_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  status ENUM('success','failed') DEFAULT 'success',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_login_history_user_id (`user_id`),
  INDEX idx_login_history_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── For existing installs ─────────────────────
CREATE TABLE IF NOT EXISTS login_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  user_agent VARCHAR(500) DEFAULT NULL,
  status ENUM('success','failed') DEFAULT 'success',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Payment Status ───────────────────────────
CREATE TABLE IF NOT EXISTS payment_status (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auction_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  status ENUM('unpaid','paid','partial') DEFAULT 'unpaid',
  paid_amount DECIMAL(12,0) DEFAULT 0,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_auction_member (`auction_id`, `member_id`),
  INDEX idx_payment_auction (`auction_id`),
  INDEX idx_payment_member (`member_id`),
  INDEX idx_payment_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── For existing installs ─────────────────────
CREATE TABLE IF NOT EXISTS payment_status (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auction_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  status ENUM('unpaid','paid','partial') DEFAULT 'unpaid',
  paid_amount DECIMAL(12,0) DEFAULT 0,
  paid_at TIMESTAMP NULL DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  updated_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_auction_member (`auction_id`, `member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Statement History ────────────────────────
CREATE TABLE IF NOT EXISTS statement_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auction_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  action ENUM('pdf','email') NOT NULL,
  net_payout DECIMAL(12,0) DEFAULT 0,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_stmt_auction (`auction_id`),
  INDEX idx_stmt_member (`member_id`),
  INDEX idx_stmt_user (`user_id`),
  INDEX idx_stmt_created (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── For existing installs ─────────────────────
CREATE TABLE IF NOT EXISTS statement_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auction_id INT UNSIGNED NOT NULL,
  member_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  action ENUM('pdf','email') NOT NULL,
  net_payout DECIMAL(12,0) DEFAULT 0,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

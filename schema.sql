-- ─────────────────────────────────────────────────────────────────
-- AuctionKai — MySQL Schema (Multi-Auction)
-- Run this entire file once in phpMyAdmin → SQL tab
-- ─────────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS `auctionkai`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `auctionkai`;

-- ── Users (login system — MUST be first due to foreign keys) ────
CREATE TABLE IF NOT EXISTS `users` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username`   VARCHAR(100) NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `email`      VARCHAR(200) DEFAULT '',
  `role`       VARCHAR(20) NOT NULL DEFAULT 'user',
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Auctions (multiple auctions) ────────────────────────────────
CREATE TABLE IF NOT EXISTS `auction` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id`    INT UNSIGNED NOT NULL,
  `name`       VARCHAR(200) NOT NULL,
  `date`       DATE         NOT NULL,
  `location`   VARCHAR(200) DEFAULT '',
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
  `sold`        TINYINT(1)   DEFAULT 1,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`auction_id`) REFERENCES `auction`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Fee Settings (per auction) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `fees` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `auction_id`      INT UNSIGNED NOT NULL,
  `entry_fee`       DECIMAL(10,0) NOT NULL DEFAULT 3000,
  `commission_rate` DECIMAL(5,2)  NOT NULL DEFAULT 3.00,
  `tax_rate`        DECIMAL(5,2)  NOT NULL DEFAULT 10.00,
  `transport_fee`   DECIMAL(10,0) NOT NULL DEFAULT 5000,
  `updated_at`      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`auction_id`) REFERENCES `auction`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Custom Deductions (per auction) ─────────────────────────────
CREATE TABLE IF NOT EXISTS `custom_deductions` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `auction_id` INT UNSIGNED NOT NULL,
  `name`       VARCHAR(200)  NOT NULL,
  `amount`     DECIMAL(10,0) NOT NULL DEFAULT 0,
  FOREIGN KEY (`auction_id`) REFERENCES `auction`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- Seed Data
-- ─────────────────────────────────────────────────────────────────

-- Default admin user (username: admin, password: password)
INSERT INTO `users` (`username`, `password`, `name`, `email`, `role`) VALUES
  ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin@auctionkai.com', 'admin');

-- ─────────────────────────────────────────────────────────────────
-- Seed: Multiple Japanese auctions
-- ─────────────────────────────────────────────────────────────────
INSERT INTO `auction` (`user_id`, `name`, `date`, `location`) VALUES
  (1, 'Nagoya Auto Auction',       CURDATE(), 'Nagoya, Aichi'),
  (1, 'Tokyo Bay Auto Auction',    CURDATE(), 'Odaiba, Tokyo'),
  (1, 'Osaka JAA Auction',         CURDATE(), 'Izumiotsu, Osaka'),
  (1, 'Yokohama Auto Auction',     CURDATE(), 'Yokohama, Kanagawa'),
  (1, 'Fukuoka Auto Auction',      CURDATE(), 'Fukuoka, Fukuoka'),
  (1, 'Sapporo Auto Auction',      CURDATE(), 'Sapporo, Hokkaido');

INSERT INTO `fees` (`auction_id`, `entry_fee`, `commission_rate`, `tax_rate`, `transport_fee`) VALUES
  (1, 3000, 3.00, 10.00, 5000),
  (2, 3500, 3.50, 10.00, 6000),
  (3, 2500, 2.50, 10.00, 4500),
  (4, 3000, 3.00, 10.00, 5500),
  (5, 2000, 2.00,  8.00, 4000),
  (6, 2500, 2.50,  8.00, 7000);

-- Custom deductions for Nagoya auction (user_id=1)
INSERT INTO `custom_deductions` (`auction_id`, `name`, `amount`) VALUES
  (1, 'Document Fee', 1500);

-- Sample members (global, user_id=1)
INSERT INTO `members` (`user_id`, `name`, `phone`, `email`) VALUES
  (1, 'Ahmad Hassan',        '090-1234-5678', 'ahmad@example.com'),
  (1, 'Mohammed Al-Rashid',  '080-9876-5432', 'm.rashid@example.com'),
  (1, 'Chen Wei',            '070-5555-0001', 'cwei@example.com'),
  (1, 'Tanaka Yuki',         '090-2222-3333', 'tanaka@example.com'),
  (1, 'Sato Kenji',          '080-4444-5555', 'sato@example.com');

-- Sample vehicles (Nagoya & Tokyo auctions, members are global)
INSERT INTO `vehicles` (`auction_id`, `member_id`, `make`, `model`, `lot`, `sold_price`, `sold`) VALUES
  (1, 1, 'Toyota',     'Prius',     'A-001',  850000, 1),
  (1, 1, 'Honda',      'Fit',       'A-002',  420000, 1),
  (1, 2, 'Nissan',     'Note',      'B-001',  680000, 1),
  (1, 2, 'Mazda',      'CX-5',      'B-002', 1250000, 1),
  (1, 3, 'Subaru',     'Forester',  'C-001',  920000, 1),
  (1, 3, 'Mitsubishi', 'Outlander', 'C-002',       0, 0),
  (2, 4, 'Honda',   'Civic',    'T-001',  780000, 1),
  (2, 4, 'Toyota',  'Corolla',  'T-002',  550000, 1),
  (2, 5, 'Lexus',   'IS 300',   'T-003', 1800000, 1);

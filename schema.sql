-- ─────────────────────────────────────────────────────────────────
-- AuctionKai — MySQL Schema
-- Run this entire file once in phpMyAdmin → SQL tab
-- ─────────────────────────────────────────────────────────────────

CREATE DATABASE IF NOT EXISTS `auctionkai`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `auctionkai`;

-- ── Auction (one active auction at a time) ───────────────────────
CREATE TABLE IF NOT EXISTS `auction` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(200) NOT NULL DEFAULT 'Nagoya Auto Auction',
  `date`       DATE         NOT NULL,
  `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Members (sellers) ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `members` (
  `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`       VARCHAR(200)  NOT NULL,
  `phone`      VARCHAR(50)   DEFAULT '',
  `email`      VARCHAR(200)  DEFAULT '',
  `created_at` TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Vehicles ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `member_id`   INT UNSIGNED NOT NULL,
  `make`        VARCHAR(100) NOT NULL,
  `model`       VARCHAR(100) DEFAULT '',
  `year`        CHAR(4)      DEFAULT '',
  `lot`         VARCHAR(50)  DEFAULT '',
  `sold_price`  DECIMAL(12,0) DEFAULT 0,
  `sold`        TINYINT(1)   DEFAULT 1,
  `created_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Fee Settings ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `fees` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `entry_fee`       DECIMAL(10,0) NOT NULL DEFAULT 3000,
  `commission_rate` DECIMAL(5,2)  NOT NULL DEFAULT 3.00,
  `tax_rate`        DECIMAL(5,2)  NOT NULL DEFAULT 10.00,
  `transport_fee`   DECIMAL(10,0) NOT NULL DEFAULT 5000,
  `updated_at`      TIMESTAMP     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Custom Deductions ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `custom_deductions` (
  `id`     INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name`   VARCHAR(200)  NOT NULL,
  `amount` DECIMAL(10,0) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- Seed default data
-- ─────────────────────────────────────────────────────────────────
INSERT INTO `auction` (`name`, `date`) VALUES ('Nagoya Auto Auction', CURDATE());

INSERT INTO `fees` (`entry_fee`, `commission_rate`, `tax_rate`, `transport_fee`)
VALUES (3000, 3.00, 10.00, 5000);

INSERT INTO `custom_deductions` (`name`, `amount`) VALUES ('Document Fee', 1500);

-- Sample members
INSERT INTO `members` (`name`, `phone`, `email`) VALUES
  ('Ahmad Hassan',        '090-1234-5678', 'ahmad@example.com'),
  ('Mohammed Al-Rashid',  '080-9876-5432', 'm.rashid@example.com'),
  ('Chen Wei',            '070-5555-0001', 'cwei@example.com');

-- Sample vehicles (member IDs 1,2,3 from above)
INSERT INTO `vehicles` (`member_id`, `make`, `model`, `year`, `lot`, `sold_price`, `sold`) VALUES
  (1, 'Toyota',     'Prius',     '2019', 'A-001',  850000, 1),
  (1, 'Honda',      'Fit',       '2018', 'A-002',  420000, 1),
  (2, 'Nissan',     'Note',      '2020', 'B-001',  680000, 1),
  (2, 'Mazda',      'CX-5',      '2021', 'B-002', 1250000, 1),
  (3, 'Subaru',     'Forester',  '2019', 'C-001',  920000, 1),
  (3, 'Mitsubishi', 'Outlander', '2018', 'C-002',       0, 0);

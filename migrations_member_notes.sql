-- ── Member Notes System ──────────────────────────────────────────
-- Add notes column to members table (global notes)
ALTER TABLE `members` ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `email`;

-- Create per-auction notes table
CREATE TABLE IF NOT EXISTS `member_auction_notes` (
  `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `auction_id`  INT UNSIGNED NOT NULL,
  `member_id`   INT UNSIGNED NOT NULL,
  `notes`       TEXT DEFAULT NULL,
  `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `auction_member` (`auction_id`, `member_id`),
  FOREIGN KEY (`auction_id`) REFERENCES `auction`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`member_id`) REFERENCES `members`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─────────────────────────────────────────────────────────────────
-- AuctionKai — Incremental Migrations
-- Run ONLY the new migrations on existing databases.
-- Never run schema.sql on production — that's for fresh installs only.
-- ─────────────────────────────────────────────────────────────────

-- ── Migration: v3.1 — Activity Log ──────────────────────────────
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

-- ── Migration: v3.2 — (no new tables, CSV import uses existing members table) ──

-- ── Future migrations go below this line ────────────────────────
-- Each migration must be idempotent (safe to run multiple times):
--   - Use CREATE TABLE IF NOT EXISTS
--   - Use ALTER TABLE ... ADD COLUMN IF NOT EXISTS
--   - Use CREATE INDEX IF NOT EXISTS
--   - Never DROP tables or columns

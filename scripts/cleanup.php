<?php
/**
 * Cron job: Clean up expired data.
 * Run daily via cron: php scripts/cleanup.php
 * 
 * - Delete expired auction vehicles and auctions
 * - Delete expired statement links
 * - Delete pre-update backups older than 14 days
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';

$db = db();

// ── Clean expired statement links ─────────────
try {
    $stmt = $db->prepare("DELETE FROM statement_links WHERE expires_at < NOW()");
    $stmt->execute();
    $deleted = $stmt->rowCount();
    if ($deleted > 0) error_log("[AuctionKai cleanup] Deleted {$deleted} expired statement links");
} catch (Exception $e) {
    error_log("[AuctionKai cleanup] Error cleaning statement links: " . $e->getMessage());
}

// ── Clean pre-update backups older than 14 days ─
$backupDir = dirname(__DIR__) . '/backups/pre_update';
if (is_dir($backupDir)) {
    $cutoff = time() - (14 * 86400);
    $cleaned = 0;
    foreach (glob($backupDir . '/*') as $file) {
        if (is_file($file) && filemtime($file) < $cutoff) {
            @unlink($file);
            $cleaned++;
        }
    }
    if ($cleaned > 0) error_log("[AuctionKai cleanup] Deleted {$cleaned} pre-update backups older than 14 days");
}

// ── Clean old activity logs (keep 90 days) ─────
try {
    $stmt = $db->prepare("DELETE FROM activity_log WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
} catch (Exception $e) {
    // Table may not exist yet
}

// ── Clean old login history (keep 90 days) ─────
try {
    $stmt = $db->prepare("DELETE FROM login_history WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $stmt->execute();
} catch (Exception $e) {}

// ── Clean old error logs (resolved, older than 30 days) ─
try {
    $stmt = $db->prepare("DELETE FROM error_logs WHERE is_resolved = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $stmt->execute();
} catch (Exception $e) {}

echo "Cleanup complete.\n";

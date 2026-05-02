<?php
/**
 * AuctionKai Automated Backup Script
 * 
 * Run via cron job:
 * Daily:   0 2 * * *   php /path/to/scripts/backup.php
 * Weekly:  0 2 * * 0   php /path/to/scripts/backup.php
 * 
 * Can also be triggered manually from admin panel.
 */

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    require_once __DIR__ . '/../includes/auth_check.php';
    require_once __DIR__ . '/../includes/db.php';
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
} else {
    require_once __DIR__ . '/../config.php';
    function db(): PDO {
        static $pdo = null;
        if ($pdo) return $pdo;
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }
}

$db = db();

// ── Settings ─────────────────────────────────
$settings = $db->query("SELECT `key`, value FROM settings WHERE `key` LIKE 'backup_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
$compress = ($settings['backup_compress'] ?? '1') === '1';
$retentionDays = (int)($settings['backup_retention_days'] ?? 30);
$backupDir = __DIR__ . '/../backups/';

if (!is_dir($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// ── Generate filename ─────────────────────────
$timestamp = date('Y-m-d_H-i-s');
$filename = "auctionkai_backup_{$timestamp}.sql";
$filepath = $backupDir . $filename;

// ── Build SQL dump ────────────────────────────
$tables = ['users','auction','members','vehicles','password_resets','settings','activity_log','login_history','payment_status'];

$sql = "-- AuctionKai Automated Backup\n";
$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$sql .= "-- Database: " . DB_NAME . "\n\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
$sql .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
$sql .= "SET NAMES utf8mb4;\n\n";

foreach ($tables as $table) {
    $check = $db->query("SHOW TABLES LIKE '$table'")->fetch();
    if (!$check) continue;

    $sql .= "-- Table: $table\n";
    $create = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
    $sql .= "DROP TABLE IF EXISTS `$table`;\n";
    $sql .= $create[1] . ";\n\n";

    $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);
    if (empty($rows)) {
        $sql .= "-- (empty table)\n\n";
        continue;
    }

    $cols = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    $colList = '`' . implode('`, `', $cols) . '`';

    $chunks = array_chunk($rows, 100);
    foreach ($chunks as $chunk) {
        $values = array_map(function($row) {
            return '(' . implode(', ', array_map(fn($v) => $v === null ? 'NULL' : "'" . addslashes($v) . "'", $row)) . ')';
        }, $chunk);
        $sql .= "INSERT INTO `$table` ($colList) VALUES\n";
        $sql .= implode(",\n", $values) . ";\n";
    }
    $sql .= "\n";
}

$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
$sql .= "-- Backup complete\n";

// ── Write file ────────────────────────────────
$written = file_put_contents($filepath, $sql);
if ($written === false) {
    $msg = "ERROR: Could not write backup file";
    if ($isCli) { echo $msg . "\n"; } else { echo json_encode(['success' => false, 'message' => $msg]); }
    exit(1);
}

// ── Compress if enabled ───────────────────────
$finalFile = $filepath;
if ($compress && function_exists('gzopen')) {
    $gzFile = $filepath . '.gz';
    $gz = gzopen($gzFile, 'wb9');
    gzwrite($gz, $sql);
    gzclose($gz);
    unlink($filepath);
    $finalFile = $gzFile;
    $filename = $filename . '.gz';
}

$fileSize = filesize($finalFile);

// ── Delete old backups ────────────────────────
$deleted = 0;
$files = glob($backupDir . '*.sql*');
if ($files) {
    foreach ($files as $file) {
        if ((time() - filemtime($file)) > ($retentionDays * 86400)) {
            unlink($file);
            $deleted++;
        }
    }
}

// ── Update last run ───────────────────────────
$nextRun = '';
$freq = $settings['backup_frequency'] ?? 'daily';
if ($freq === 'daily') $nextRun = date('Y-m-d H:i:s', strtotime('+1 day'));
if ($freq === 'weekly') $nextRun = date('Y-m-d H:i:s', strtotime('+7 days'));
if ($freq === 'monthly') $nextRun = date('Y-m-d H:i:s', strtotime('+1 month'));

$db->prepare("INSERT INTO settings (`key`, `value`) VALUES ('backup_last_run', ?), ('backup_next_run', ?) ON DUPLICATE KEY UPDATE value = VALUES(`value`)")->execute([date('Y-m-d H:i:s'), $nextRun]);

// ── Log activity ──────────────────────────────
if (!$isCli) {
    require_once __DIR__ . '/../includes/activity.php';
    $adminId = (int)($_SESSION['user_id'] ?? 0);
    logActivity($db, $adminId, 'backup.auto', 'system', 0, "Automated backup: {$filename} (" . round($fileSize/1024, 1) . " KB), deleted {$deleted} old backups");
}

// ── Output result ─────────────────────────────
$result = [
    'success' => true,
    'filename' => $filename,
    'size' => $fileSize,
    'size_fmt' => round($fileSize/1024, 1) . ' KB',
    'deleted' => $deleted,
    'next_run' => $nextRun,
    'message' => "Backup created: {$filename} (" . round($fileSize/1024, 1) . " KB)"
];

if ($isCli) {
    echo "✓ " . $result['message'] . "\n";
    echo "  Deleted {$deleted} old backup(s)\n";
    echo "  Next run: {$nextRun}\n";
} else {
    header('Content-Type: application/json');
    echo json_encode($result);
}

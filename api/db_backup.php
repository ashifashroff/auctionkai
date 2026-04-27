<?php
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';

// Only admin can access
if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$db = db();
$filename = 'auctionkai_backup_' . date('Y-m-d_H-i-s') . '.sql';

// Set headers for file download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// ── Header comment ──────────────────────────
echo "-- =============================================\n";
echo "-- AuctionKai Database Backup\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- By: " . ($_SESSION['user_name'] ?? 'Admin') . "\n";
echo "-- Designed & Developed by Mirai Global Solutions\n";
echo "-- =============================================\n\n";
echo "SET FOREIGN_KEY_CHECKS=0;\n";
echo "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
echo "SET NAMES utf8mb4;\n\n";

// ── Tables to backup ────────────────────────
$tables = [
    'users',
    'auction',
    'members',
    'vehicles',
    'fees',
    'custom_deductions',
    'password_resets',
    'settings',
    'activity_log',
];

foreach ($tables as $table) {
    // Check if table exists
    $check = $db->query("SHOW TABLES LIKE '$table'")->fetch();
    if (!$check) continue;

    echo "-- ── Table: $table ─────────────────────\n";

    // DROP + CREATE TABLE
    $createStmt = $db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);

    echo "DROP TABLE IF EXISTS `$table`;\n";
    echo $createStmt[1] . ";\n\n";

    // Fetch all rows
    $rows = $db->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_NUM);

    if (empty($rows)) {
        echo "-- (no data)\n\n";
        continue;
    }

    // Get column names
    $cols = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);

    $colList = '`' . implode('`, `', $cols) . '`';

    // Write INSERT statements in batches of 100
    $batch = [];
    foreach ($rows as $row) {
        $values = array_map(function($val) use ($db) {
            if ($val === null) return 'NULL';
            return "'" . addslashes($val) . "'";
        }, $row);
        $batch[] = '(' . implode(', ', $values) . ')';

        if (count($batch) >= 100) {
            echo "INSERT INTO `$table` ($colList) VALUES\n";
            echo implode(",\n", $batch) . ";\n";
            $batch = [];
        }
    }
    if (!empty($batch)) {
        echo "INSERT INTO `$table` ($colList) VALUES\n";
        echo implode(",\n", $batch) . ";\n";
    }

    echo "\n";
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
echo "-- ── Backup complete ─────────────────────\n";

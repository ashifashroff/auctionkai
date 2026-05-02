<?php
require_once __DIR__ . '/../includes/admin_check.php';

$filename = basename($_GET['file'] ?? '');
$backupDir = __DIR__ . '/../backups/';
$filepath = $backupDir . $filename;

if (empty($filename) || !preg_match('/^auctionkai_backup_[\w\-\.]+\.sql(\.gz)?$/', $filename) || !file_exists($filepath)) {
    http_response_code(404);
    exit('File not found');
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/activity.php';

logActivity($db, $userId, 'backup.download', 'system', 0, "Downloaded backup: {$filename}");

$contentType = str_ends_with($filename, '.gz') ? 'application/gzip' : 'application/octet-stream';
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache');
readfile($filepath);
exit;

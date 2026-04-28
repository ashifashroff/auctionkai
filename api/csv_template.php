<?php
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="auctionkai_members_template.csv"');
header('Cache-Control: no-cache');

// BOM for Excel UTF-8 compatibility
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, ['name', 'phone', 'email']);

// Example rows
fputcsv($out, ['Ahmad Hassan', '090-1234-5678', 'ahmad@example.com']);
fputcsv($out, ['Mohammed Al-Rashid', '080-9876-5432', 'm.rashid@example.com']);
fputcsv($out, ['Chen Wei', '070-5555-0001', 'cwei@example.com']);
fputcsv($out, ['Tanaka Hiroshi', '090-0000-1111', '']);

fclose($out);

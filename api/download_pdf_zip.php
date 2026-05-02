<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/branding.php';
require_once __DIR__ . '/../includes/activity.php';

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZIP extension not available on this server. Please contact your host.');
}

$auctionId = (int)($_GET['auction_id'] ?? 0);
if (!$auctionId) {
    http_response_code(400);
    exit('Missing auction ID');
}

$stmt = $db->prepare("SELECT * FROM auction WHERE id=? AND user_id=?");
$stmt->execute([$auctionId, $userId]);
$auction = $stmt->fetch();
if (!$auction) {
    http_response_code(404);
    exit('Auction not found');
}

$brand = loadBranding($db);
$brandName = $brand['brand_name'] ?? 'AuctionKai';
$accentColor = sanitizeColor($brand['brand_accent_color'] ?? '#D4A84B');
$footerText = $brand['brand_footer_text'] ?? 'Designed & Developed by Mirai Global Solutions';

// Fetch members with sold vehicles
$stmt = $db->prepare("SELECT DISTINCT m.* FROM members m JOIN vehicles v ON v.member_id = m.id WHERE v.auction_id = ? AND v.sold = 1 AND m.user_id = ? ORDER BY m.name");
$stmt->execute([$auctionId, $userId]);
$members = $stmt->fetchAll();

if (empty($members)) {
    http_response_code(404);
    exit('No sold vehicles found for this auction');
}

// Fetch all vehicles
$stmt = $db->prepare("SELECT v.* FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.auction_id = ? AND m.user_id = ? ORDER BY v.lot");
$stmt->execute([$auctionId, $userId]);
$allVehicles = $stmt->fetchAll();

$commissionFee = (float)($auction['commission_fee'] ?? 3300);

function generateMemberPDF(array $member, array $vehicles, array $auction, float $commissionFee, string $brandName, string $accentColor, string $footerText): string {
    $s = calcStatement((int)$member['id'], $vehicles, $commissionFee);
    if ($s['count'] === 0) return '';

    $rows = '';
    foreach ($s['mv'] as $v) {
        $tax = round((float)$v['sold_price'] * 0.10);
        $recycle = (float)($v['recycle_fee'] ?? 0);
        $rows .= "<tr><td>" . htmlspecialchars($v['lot'] ?: '—') . "</td><td>" . htmlspecialchars($v['make'] . ' ' . $v['model']) . "</td><td>" . htmlspecialchars($v['year'] ?? '—') . "</td><td class='r'>¥" . number_format((float)$v['sold_price']) . "</td><td class='r'>¥" . number_format($tax) . "</td><td class='r'>" . ($recycle > 0 ? '¥' . number_format($recycle) : '—') . "</td></tr>";
    }

    $dedRows = '';
    if ($s['listingFeeTotal'] > 0) $dedRows .= "<div class='row dim'><span>Listing Fee ×{$s['count']}</span><span>−¥" . number_format($s['listingFeeTotal']) . "</span></div>";
    if ($s['soldFeeTotal'] > 0) $dedRows .= "<div class='row dim'><span>Sold Fee ×{$s['count']}</span><span>−¥" . number_format($s['soldFeeTotal']) . "</span></div>";
    if ($s['nagareFeeTotal'] > 0) $dedRows .= "<div class='row dim'><span>Nagare Fee ×{$s['unsoldCount']}</span><span>−¥" . number_format($s['nagareFeeTotal']) . "</span></div>";
    if ($s['otherFeeTotal'] > 0) $dedRows .= "<div class='row dim'><span>Other Fee</span><span>−¥" . number_format($s['otherFeeTotal']) . "</span></div>";
    if ($s['commissionTotal'] > 0) $dedRows .= "<div class='row dim'><span>Commission</span><span>−¥" . number_format($s['commissionTotal']) . "</span></div>";

    $aName = htmlspecialchars($auction['name']);
    $aDate = htmlspecialchars($auction['date']);
    $mName = htmlspecialchars($member['name']);
    $mPhone = htmlspecialchars($member['phone'] ?? '');
    $mEmail = htmlspecialchars($member['email'] ?? '');

    return "<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<title>Statement — {$mName}</title>
<link href='https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap' rel='stylesheet'>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans JP',sans-serif;padding:40px 44px;color:#111;font-size:13px;line-height:1.5}
.hdr{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid #111;padding-bottom:14px;margin-bottom:24px}
.brand{font-size:24px;font-weight:700;letter-spacing:-.5px}
.brand span{color:{$accentColor}}
.sub{font-size:11px;color:#666;margin-top:3px}
.meta{text-align:right;font-size:12px;color:#444}
.meta strong{font-size:16px;color:#111;display:block;margin-bottom:2px}
.sec{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#999;margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid #eee}
table{width:100%;border-collapse:collapse;margin-bottom:24px;font-size:12px}
th{background:#f5f5f5;padding:7px 10px;text-align:left;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#555}
td{padding:8px 10px;border-bottom:1px solid #f0f0f0}
.r{text-align:right;font-family:'Space Mono',monospace}
.total-row{font-weight:700;background:#f5f5f5}
.fees{background:#fafafa;border:1px solid #e8e8e8;border-radius:6px;padding:16px;margin-bottom:20px}
.row{display:flex;justify-content:space-between;padding:5px 0;font-size:13px;font-family:'Space Mono',monospace}
.row.dim{color:#777;font-size:12px}
.row.sep{border-top:1px dashed #ddd;margin-top:6px;padding-top:10px}
.row.total{border-top:2px solid #ccc;margin-top:6px;padding-top:10px;font-weight:700}
.net{background:#111;color:#fff;padding:16px 20px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;margin-bottom:24px}
.net-l{font-size:13px;font-weight:500}
.net-n{font-size:26px;font-weight:700;font-family:'Space Mono',monospace;color:{$accentColor}}
.footer{text-align:center;font-size:10px;color:#bbb;border-top:1px solid #eee;padding-top:12px;margin-top:8px}
@media print{body{padding:24px}@page{margin:15mm;size:A4}}
</style>
</head>
<body>
<div class='hdr'>
  <div><div class='brand'>⚡ <span>{$brandName}</span> 精算書</div><div class='sub'>Settlement Statement · {$aName}</div></div>
  <div class='meta'><strong>{$mName}</strong>{$mPhone}<br>{$mEmail}<br><br>Date: {$aDate}</div>
</div>
<div class='sec'>Sold Vehicles ({$s['count']} units)</div>
<table><thead><tr><th>Lot #</th><th>Vehicle</th><th>Year</th><th class='r'>Sold Price</th><th class='r'>Tax 10%</th><th class='r'>Recycle</th></tr></thead><tbody>{$rows}
<tr class='total-row'><td colspan='3'>Total Received</td><td class='r'>¥" . number_format($s['grossSales']) . "</td><td class='r'>¥" . number_format($s['taxTotal']) . "</td><td class='r'>¥" . number_format($s['recycleTotal']) . "</td></tr>
</tbody></table>
<div class='sec'>Fee Breakdown</div>
<div class='fees'>
  <div class='row'><span>Total Received</span><span>¥" . number_format($s['totalReceived']) . "</span></div>
  <div class='row sep dim'></div>
  {$dedRows}
  <div class='row total'><span>Total Deductions</span><span>−¥" . number_format($s['totalDed']) . "</span></div>
</div>
<div class='net'><span class='net-l'>NET PAYOUT / お支払い額</span><span class='net-n'>¥" . number_format($s['netPayout']) . "</span></div>
<div class='footer'>{$aName} · {$aDate} · {$footerText}</div>
</body></html>";
}

// ── Create ZIP ────────────────────────────────
$tempDir = sys_get_temp_dir() . '/auctionkai_zip_' . uniqid();
mkdir($tempDir, 0755, true);

$auctionSlug = preg_replace('/[^a-z0-9]+/', '-', strtolower($auction['name']));
$zipFilename = "statements_{$auctionSlug}_" . date('Y-m-d') . ".zip";
$zipPath = $tempDir . '/' . $zipFilename;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
    http_response_code(500);
    exit('Could not create ZIP file');
}

$includedCount = 0;

foreach ($members as $member) {
    $html = generateMemberPDF($member, $allVehicles, $auction, $commissionFee, $brandName, $accentColor, $footerText);
    if (empty($html)) continue;

    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $member['name']);
    $zip->addFromString("statement_{$safeName}.html", $html);
    $includedCount++;

    // Log to statement history
    try {
        $s = calcStatement((int)$member['id'], $allVehicles, $commissionFee);
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
        $db->prepare("INSERT INTO statement_history (auction_id, member_id, user_id, action, net_payout, ip_address) VALUES (?, ?, ?, 'pdf', ?, ?)")
            ->execute([$auctionId, (int)$member['id'], $userId, $s['netPayout'], $ip]);
    } catch (Exception $e) {}
}

// Add README
$readme = "AuctionKai Statement Package\n============================\nAuction: {$auction['name']}\nDate: {$auction['date']}\nGenerated: " . date('Y-m-d H:i:s') . "\nMembers: {$includedCount}\n\nHOW TO USE:\n1. Open each HTML file in your browser\n2. Press Ctrl+P (or Cmd+P on Mac)\n3. Select 'Save as PDF' as the destination\n4. Click Save\n\nFiles in this package:\n";
foreach ($members as $m) {
    $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $m['name']);
    $readme .= "- statement_{$safeName}.html\n";
}
$readme .= "\n{$footerText}\n";
$zip->addFromString('README.txt', $readme);

$zip->close();

logActivity($db, $userId, 'pdf.zip_download', 'auction', $auctionId, "ZIP download: {$includedCount} statements for auction: {$auction['name']}");

// ── Stream ZIP ────────────────────────────────
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache');
readfile($zipPath);

unlink($zipPath);
rmdir($tempDir);
exit;

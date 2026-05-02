<?php
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/maintenance_check.php';
require_once __DIR__ . '/includes/branding.php';
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

if (empty($_SESSION['user_id'])) { header('Location: auth/login.php'); exit; }

$db = db();

// Maintenance check
$userRole = $_SESSION['user_role'] ?? 'user';
checkMaintenanceMode($db, $userRole);

$brand = loadBranding($db);
$accentColor = sanitizeColor($brand['brand_accent_color']);
$userId = (int)$_SESSION['user_id'];

$activeAuctionId = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;
if (!$activeAuctionId) {
    $first = $db->prepare("SELECT id FROM auction WHERE user_id=? ORDER BY id LIMIT 1");
    $first->execute([$userId]);
    $first = $first->fetch();
    $activeAuctionId = $first ? (int)$first['id'] : 0;
}

$auction = null;
if ($activeAuctionId) {
    $stmt = $db->prepare("SELECT * FROM auction WHERE id=? AND user_id=?");
    $stmt->execute([$activeAuctionId, $userId]);
    $auction = $stmt->fetch();
}

$members  = $auction ? $db->prepare("SELECT * FROM members WHERE user_id=? ORDER BY id") : null;
if ($members) { $members->execute([$userId]); $members = $members->fetchAll(); } else { $members = []; }

$vehicles = $activeAuctionId ? $db->prepare("SELECT v.* FROM vehicles v JOIN members m ON v.member_id=m.id WHERE v.auction_id=? AND m.user_id=? ORDER BY v.id") : null;
if ($vehicles) { $vehicles->execute([$activeAuctionId, $userId]); $vehicles = $vehicles->fetchAll(); } else { $vehicles = []; }

$printAll = isset($_GET['all']);
$memberId = isset($_GET['member']) ? (int)$_GET['member'] : null;

$targets = $printAll
    ? $members
    : array_values(array_filter($members, fn($m) => (int)$m['id'] === $memberId));

if (empty($targets)) { echo 'No members found.'; exit; }

// Fetch payment statuses
$paymentStatuses = [];
if ($activeAuctionId) {
    $psq = $db->prepare("SELECT member_id, status, paid_at FROM payment_status WHERE auction_id = ?");
    $psq->execute([$activeAuctionId]);
    foreach ($psq->fetchAll() as $ps) { $paymentStatuses[$ps['member_id']] = $ps; }
}

function renderStatement(array $m, array $s, array $auction, string $payStatus = 'unpaid'): string {
    $rows = '';
    foreach ($s['mv'] as $v) {
        $net = (float)$v['sold_price'] + round((float)$v['sold_price'] * 0.10) + (float)($v['recycle_fee'] ?? 0) - (float)($v['listing_fee'] ?? 0) - (float)($v['sold_fee'] ?? 0);
        $rows .= "<tr><td>" . h($v['lot'] ?: '—') . "</td><td>" . h($v['make'] . ' ' . $v['model']) . "</td><td class='r'>" . fmt((float)$v['sold_price']) . "</td><td class='r'>" . fmt(round((float)$v['sold_price'] * 0.10)) . "</td><td class='r'>" . fmt((float)($v['recycle_fee'] ?? 0)) . "</td><td class='r'>−" . fmt((float)($v['listing_fee'] ?? 0)) . "</td><td class='r'>−" . fmt((float)($v['sold_fee'] ?? 0)) . "</td><td class='r' style='font-weight:700'>" . fmt($net) . "</td></tr>";
    }
    $uRows = '';
    foreach ($s['uv'] ?? [] as $v) {
        $uRows .= "<tr><td>" . h($v['lot'] ?: '—') . "</td><td>" . h($v['make'] . ' ' . $v['model']) . "</td><td class='r'>−" . fmt((float)($v['nagare_fee'] ?? 0)) . "</td><td class='r' style='font-weight:700;color:#e74c3c'>−" . fmt((float)($v['nagare_fee'] ?? 0)) . "</td></tr>";
    }
    $exp = !empty($auction['expires_at']) ? ' · Expires: ' . h($auction['expires_at']) : '';
    return "
    <div class='page'>
      " . ($payStatus === 'paid' ? "<div style='position:absolute;top:40px;right:44px;transform:rotate(-15deg);border:3px solid #2E7D52;color:#2E7D52;padding:6px 16px;border-radius:4px;font-size:22px;font-weight:900;opacity:0.35;letter-spacing:2px;font-family:Space Mono,monospace'>PAID</div>" : ($payStatus === 'partial' ? "<div style='position:absolute;top:40px;right:44px;transform:rotate(-15deg);border:3px solid #B8912A;color:#B8912A;padding:6px 16px;border-radius:4px;font-size:18px;font-weight:900;opacity:0.35;letter-spacing:2px;font-family:Space Mono,monospace'>PARTIAL</div>" : '')) . "
      <div class='hdr'>
        <div><div class='brand'>" . h($brand['brand_name']) . " <span>精算書</span></div><div class='sub'>Settlement Statement · " . h($auction['name']) . $exp . "</div>" . ((!empty($brand['brand_email']) || !empty($brand['brand_phone'])) ? "<div style='font-size:11px;color:#666;margin-top:4px'>" . (!empty($brand['brand_email']) ? '✉ ' . h($brand['brand_email']) . ' ' : '') . (!empty($brand['brand_phone']) ? '📞 ' . h($brand['brand_phone']) : '') . "</div>" : "") . "</div>" . h($auction['name']) . $exp . "</div></div>
        <div class='meta'><strong>" . h($m['name']) . "</strong> " . h($m['phone']) . "<br>" . h($m['email']) . "<br><br>Date: " . h($auction['date']) . "</div>
      </div>
      <div class='sec'>Sold Vehicles ({$s['count']} units)</div>
      <table>
        <thead><tr><th>Lot #</th><th>Vehicle</th><th class='r'>Sold Price</th><th class='r'>Tax 10%</th><th class='r'>Recycle</th><th class='r'>Listing</th><th class='r'>Sold Fee</th><th class='r'>Net</th></tr></thead>
        <tbody>{$rows}</tbody>
      </table>
      " . ($s['unsoldCount'] > 0 ? "
      <div class='sec'>Unsold Vehicles ({$s['unsoldCount']} units)</div>
      <table>
        <thead><tr><th>Lot #</th><th>Vehicle</th><th class='r'>Nagare</th><th class='r'>Total</th></tr></thead>
        <tbody>{$uRows}</tbody>
      </table>
      " : "") . "
      <div class='sec'>Fee Breakdown</div>
      <div class='fees'>
        <div class='row'><span>Gross Sales</span><span>" . fmt($s['grossSales']) . "</span></div>
        <div class='row'><span>+ Consumption Tax 10%</span><span>" . fmt($s['taxTotal']) . "</span></div>
        <div class='row'><span>+ Recycle Fees</span><span>" . fmt($s['recycleTotal']) . "</span></div>
        <div class='row' style='font-weight:700;border-top:1px solid #ccc;padding-top:8px'><span>Total Received</span><span>" . fmt($s['totalReceived']) . "</span></div>
        " . ($s['listingFeeTotal'] > 0 ? "<div class='row dim'><span>− Listing Fees</span><span>" . fmt($s['listingFeeTotal']) . "</span></div>" : "") . "
        " . ($s['soldFeeTotal'] > 0 ? "<div class='row dim'><span>− Sold Fees</span><span>" . fmt($s['soldFeeTotal']) . "</span></div>" : "") . "
        " . ($s['nagareFeeTotal'] > 0 ? "<div class='row dim'><span>− Nagare Fees</span><span>" . fmt($s['nagareFeeTotal']) . "</span></div>" : "") . "
        " . ($s['commissionTotal'] > 0 ? "<div class='row dim'><span>− Commission ¥" . number_format($s['commissionFee']) . "/member</span><span>" . fmt($s['commissionTotal']) . "</span></div>" : "") . "
        <div class='row total'><span>Total Deductions</span><span>−" . fmt($s['totalDed']) . "</span></div>
      </div>
      <div class='net'><div class='net-l'>NET PAYOUT / お支払い額</div><div class='net-n'>" . fmt($s['netPayout']) . "</div></div>
      <div class='footer'>" . h($auction['name']) . " · " . h($auction['date']) . $exp . " · " . h($brand['brand_name']) . " · " . h($brand['brand_footer_text']) . "</div>
    </div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Statements — <?= h($brand['brand_name']) ?> · <?= h($auction['name'] ?? 'Auction') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/pdf.css?v=3.3">
</head>
<body>

<div class="ctrl">
  <span>⚡ AuctionKai</span>
  <a href="index.php?tab=statements<?= $activeAuctionId ? '&auction_id='.$activeAuctionId : '' ?>">← Back</a>
  <span style="margin-left:auto;color:#7A94A8;font-size:12px"><?= count($targets) ?> statement<?= count($targets)>1?'s':'' ?> · <?= h($auction['name'] ?? '') ?></span>
  <button class="bp" onclick="window.print()">🖨 Print / Save PDF</button>
</div>

<?php foreach ($targets as $m):
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300));
    if ($s['count'] === 0) continue;
    $ps = $paymentStatuses[$m['id']] ?? null;
    $payStatus = $ps['status'] ?? 'unpaid';
    echo renderStatement($m, $s, $auction, $payStatus);

    // Log PDF generation to statement_history
    try {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]);
        $db->prepare("INSERT INTO statement_history (auction_id, member_id, user_id, action, net_payout, ip_address) VALUES (?, ?, ?, 'pdf', ?, ?)")->execute([$activeAuctionId, (int)$m['id'], $userId, $s['netPayout'], $ip]);
    } catch (Exception $e) {}

endforeach; ?>

</body>
</html>

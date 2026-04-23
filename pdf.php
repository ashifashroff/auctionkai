<?php
require_once 'config.php';

function fmt(float $n): string { return '¥' . number_format(round($n)); }
function h(string $s): string  { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function calcStatement(int $memberId, array $vehicles, float $commissionFee): array {
    $all = array_values(array_filter($vehicles, fn($v) => (int)$v['member_id'] === $memberId));
    $mv = array_values(array_filter($all, fn($v) => $v['sold']));
    $uv = array_values(array_filter($all, fn($v) => !$v['sold']));
    $count       = count($mv);
    $unsoldCount = count($uv);
    $grossSales  = array_sum(array_column($mv, 'sold_price'));
    $taxTotal    = array_sum(array_map(fn($v) => round((float)$v['sold_price'] * 0.10), $mv));
    $recycleTotal= array_sum(array_map(fn($v) => (float)($v['recycle_fee'] ?? 0), $mv));
    $listingFeeTotal = array_sum(array_map(fn($v) => (float)($v['listing_fee'] ?? 0), $mv));
    $soldFeeTotal    = array_sum(array_map(fn($v) => (float)($v['sold_fee'] ?? 0), $mv));
    $nagareFeeTotal  = array_sum(array_map(fn($v) => (float)($v['nagare_fee'] ?? 0), $uv)); // nagare for unsold only
    $otherFeeTotal   = array_sum(array_map(fn($v) => (float)($v['other_fee'] ?? 0), $all));

    $commissionTotal = $commissionFee;
    $totalReceived = $grossSales + $taxTotal + $recycleTotal;
    $totalVehicleDed = $listingFeeTotal + $soldFeeTotal + $nagareFeeTotal + $otherFeeTotal;
    $totalDed = $totalVehicleDed + $commissionTotal;
    $netPayout = $count > 0 ? $totalReceived - $totalDed : 0;
    return compact('mv','uv','count','unsoldCount','grossSales','taxTotal','recycleTotal','listingFeeTotal','soldFeeTotal','nagareFeeTotal','otherFeeTotal','commissionTotal','commissionFee','totalReceived','totalVehicleDed','totalDed','netPayout');
}

$db = db();

// Determine auction
$activeAuctionId = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;
if (!$activeAuctionId) {
    $first = $db->query("SELECT id FROM auction ORDER BY id LIMIT 1")->fetch();
    $activeAuctionId = $first ? (int)$first['id'] : 0;
}

$auction  = $activeAuctionId ? $db->query("SELECT * FROM auction WHERE id=" . (int)$activeAuctionId)->fetch() : null;
$members  = $activeAuctionId ? $db->query("SELECT * FROM members m WHERE m.user_id=" . (int)($auction['user_id'] ?? 0) . " ORDER BY m.id")->fetchAll() : [];
$vehicles = $activeAuctionId ? $db->query("SELECT v.* FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.auction_id=" . (int)$activeAuctionId . " ORDER BY v.id")->fetchAll() : [];

$printAll = isset($_GET['all']);
$memberId = isset($_GET['member']) ? (int)$_GET['member'] : null;

$targets = $printAll
    ? $members
    : array_values(array_filter($members, fn($m) => (int)$m['id'] === $memberId));

if (empty($targets)) { echo 'No members found.'; exit; }

function renderStatement(array $m, array $s, array $auction): string {
    $rows = '';
    foreach ($s['mv'] as $v) {
        $rows .= "<tr><td>" . h($v['lot'] ?: '—') . "</td><td>" . h($v['make'] . ' ' . $v['model']) . "</td><td class='r'>" . fmt((float)$v['sold_price']) . "</td><td class='r'>" . fmt(round((float)$v['sold_price'] * 0.10)) . "</td><td class='r'>" . fmt((float)($v['recycle_fee'] ?? 0)) . "</td><td class='r'>−" . fmt((float)($v['listing_fee'] ?? 0)) . "</td><td class='r'>−" . fmt((float)($v['sold_fee'] ?? 0)) . "</td><td class='r'>−" . fmt((float)($v['nagare_fee'] ?? 0)) . "</td><td class='r'>−" . fmt((float)($v['other_fee'] ?? 0)) . "</td><td class='r' style='font-weight:700'>" . fmt((float)$v['sold_price'] + round((float)$v['sold_price'] * 0.10) + (float)($v['recycle_fee'] ?? 0) - (float)($v['listing_fee'] ?? 0) - (float)($v['sold_fee'] ?? 0) - (float)($v['nagare_fee'] ?? 0) - (float)($v['other_fee'] ?? 0)) . "</td></tr>";
    }
    $uRows = '';
    foreach ($s['uv'] ?? [] as $v) {
        $uRows .= "<tr><td>" . h($v['lot'] ?: '—') . "</td><td>" . h($v['make'] . ' ' . $v['model']) . "</td><td class='r'>−" . fmt((float)($v['nagare_fee'] ?? 0)) . "</td><td class='r'>−" . fmt((float)($v['other_fee'] ?? 0)) . "</td><td class='r' style='font-weight:700;color:#e74c3c'>−" . fmt((float)($v['nagare_fee'] ?? 0) + (float)($v['other_fee'] ?? 0)) . "</td></tr>";
    }
    $exp = !empty($auction['expires_at']) ? ' · Expires: ' . h($auction['expires_at']) : '';
    return "
    <div class='page'>
      <div class='hdr'>
        <div><div class='brand'>Auction<span>Kai</span> 精算書</div><div class='sub'>Settlement Statement · " . h($auction['name']) . $exp . "</div></div>
        <div class='meta'><strong>" . h($m['name']) . "</strong>" . h($m['phone']) . "<br>" . h($m['email']) . "<br><br>Date: " . h($auction['date']) . "</div>
      </div>
      <div class='sec'>Sold Vehicles ({$s['count']} units)</div>
      <table>
        <thead><tr><th>Lot #</th><th>Vehicle</th><th class='r'>Sold Price</th><th class='r'>Tax 10%</th><th class='r'>Recycle</th><th class='r'>Listing</th><th class='r'>Sold Fee</th><th class='r'>Nagare</th><th class='r'>Other</th><th class='r'>Net</th></tr></thead>
        <tbody>{$rows}<tr class='tr'><td colspan='2'>Totals</td><td class='r'>" . fmt($s['grossSales']) . "</td><td class='r'>" . fmt($s['taxTotal']) . "</td><td class='r'>" . fmt($s['recycleTotal']) . "</td><td class='r'>" . fmt($s['listingFeeTotal']) . "</td><td class='r'>" . fmt($s['soldFeeTotal']) . "</td><td class='r'>" . fmt($s['nagareFeeTotal']) . "</td><td class='r'>" . fmt($s['otherFeeTotal']) . "</td><td class='r' style='font-weight:700'>" . fmt($s['netPayout']) . "</td></tr></tbody>
      </table>
      " . ($s['unsoldCount'] > 0 ? "
      <div class='sec'>Unsold Vehicles ({$s['unsoldCount']} units)</div>
      <table>
        <thead><tr><th>Lot #</th><th>Vehicle</th><th class='r'>Nagare</th><th class='r'>Other</th><th class='r'>Total</th></tr></thead>
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
        " . ($s['otherFeeTotal'] > 0 ? "<div class='row dim'><span>− Other Fees</span><span>" . fmt($s['otherFeeTotal']) . "</span></div>" : "") . "
        " . ($s['commissionTotal'] > 0 ? "<div class='row dim'><span>− Commission ¥" . number_format($s['commissionFee']) . "/member</span><span>" . fmt($s['commissionTotal']) . "</span></div>" : "") . "
        <div class='row total'><span>Total Deductions</span><span>−" . fmt($s['totalDed']) . "</span></div>
      </div>
      <div class='net'><div class='net-l'>NET PAYOUT / お支払い額</div><div class='net-n'>" . fmt($s['netPayout']) . "</div></div>
      <div class='footer'>" . h($auction['name']) . " · " . h($auction['date']) . $exp . " · AuctionKai Settlement System</div>
    </div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Statements — <?= h($auction['name'] ?? 'AuctionKai') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/pdf.css">
</head>
<body>
<div class="ctrl">
  <span>⚡ AuctionKai</span>
  <span style="color:#3A5570">|</span>
  <a href="index.php?tab=statements<?= $activeAuctionId ? '&auction_id='.$activeAuctionId : '' ?>">← Back</a>
  <span style="margin-left:auto;color:#3A5570"><?= count($targets) ?> statement<?= count($targets)>1?'s':'' ?> · <?= h($auction['name'] ?? '') ?></span>
  <button class="bp" onclick="window.print()">🖨 Print / Save PDF</button>
</div>

<?php foreach ($targets as $m):
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300));
    if ($s['count'] === 0) continue;
    echo renderStatement($m, $s, $auction);
endforeach; ?>

<script>
<?php if (!$printAll && $memberId): ?>
window.addEventListener('load', () => setTimeout(() => window.print(), 800));
<?php endif; ?>
</script>
</body>
</html>

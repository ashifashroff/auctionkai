<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';

$db = db();
$userId = (int)($_SESSION['user_id'] ?? 0);

// Fetch auction
$auctionId = (int)($_GET['auction_id'] ?? 0);
if (!$auctionId) {
    header('Location: index.php?tab=statements');
    exit;
}

// Verify ownership
$stmt = $db->prepare("SELECT * FROM auction WHERE id=? AND user_id=?");
$stmt->execute([$auctionId, $userId]);
$auction = $stmt->fetch();
if (!$auction) {
    header('Location: index.php?tab=statements');
    exit;
}

// Fetch all vehicles for this auction
$stmt = $db->prepare("
    SELECT v.*, m.name as member_name
    FROM vehicles v
    JOIN members m ON v.member_id = m.id
    WHERE v.auction_id = ?
    AND m.user_id = ?
    ORDER BY m.name, v.lot
");
$stmt->execute([$auctionId, $userId]);
$allVehicles = $stmt->fetchAll();

// Fetch commission fee
$commissionFee = (float)($auction['commission_fee'] ?? DEFAULT_COMMISSION_FEE);

// Get unique members from vehicles
$memberIds = array_unique(array_map(fn($v) => (int)$v['member_id'], $allVehicles));
$members = [];
if (!empty($memberIds)) {
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $stmt = $db->prepare("SELECT * FROM members WHERE id IN ($placeholders) AND user_id=? ORDER BY name");
    $params = array_merge($memberIds, [$userId]);
    $stmt->execute($params);
    $members = $stmt->fetchAll();
}

// Calculate grand totals
$grandGross = 0;
$grandPayout = 0;
$grandTotalDed = 0;
$grandSold = 0;
$grandUnsold = 0;

$memberStats = [];
foreach ($members as $m) {
    $s = calcStatement((int)$m['id'], $allVehicles, $commissionFee);
    $memberStats[$m['id']] = $s;
    $grandGross += $s['grossSales'];
    $grandPayout += $s['netPayout'];
    $grandTotalDed += $s['totalDed'];
    $grandSold += $s['count'];
    $grandUnsold += $s['unsoldCount'];
}

$grandVehicles = count($allVehicles);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Auction Summary — <?= h($auction['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/summary.css">
</head>
<body>

<!-- Screen controls -->
<div class="ctrl">
  <span class="ctrl-brand">⚡ AuctionKai</span>
  <a href="index.php?tab=statements&auction_id=<?= $auctionId ?>" class="btn-back">← Back</a>
  <span style="margin-left:auto;color:#7A94A8;font-size:12px"><?= h($auction['name']) ?> · <?= h($auction['date']) ?></span>
  <button class="btn-print" onclick="window.print()">🖨 Print Summary</button>
</div>

<div class="page">

<!-- Header -->
<div class="report-hdr">
  <div>
    <div class="report-brand">Auction<span>Kai</span> 精算集計表</div>
    <div class="report-title">Auction Summary Report</div>
  </div>
  <div class="report-meta">
    <strong><?= h($auction['name']) ?></strong><br>
    Date: <?= h($auction['date']) ?><br>
    Generated: <?= date('Y-m-d H:i') ?><br>
    Members: <?= count($members) ?> · Vehicles: <?= $grandVehicles ?>
  </div>
</div>

<!-- Grand Totals Cards -->
<div class="totals-row">
  <div class="total-card">
    <div class="total-card-num"><?= $grandVehicles ?></div>
    <div class="total-card-label">Total Vehicles</div>
  </div>
  <div class="total-card">
    <div class="total-card-num"><?= fmt($grandGross) ?></div>
    <div class="total-card-label">Gross Sales</div>
  </div>
  <div class="total-card">
    <div class="total-card-num">−<?= fmt($grandTotalDed) ?></div>
    <div class="total-card-label">Total Deductions</div>
  </div>
  <div class="total-card">
    <div class="total-card-num"><?= fmt($grandPayout) ?></div>
    <div class="total-card-label">Net Payout</div>
  </div>
</div>

<!-- Member Summary Table -->
<table class="summary-table">
  <thead>
    <tr>
      <th>#</th>
      <th>Member Name</th>
      <th class="r">Sold</th>
      <th class="r">Unsold</th>
      <th class="r">Gross Sales</th>
      <th class="r">Deductions</th>
      <th class="r">Commission</th>
      <th class="r">Net Payout</th>
    </tr>
  </thead>
  <tbody>
  <?php $i = 0; foreach ($members as $m): $i++; $s = $memberStats[$m['id']]; ?>
    <tr>
      <td><?= $i ?></td>
      <td><strong><?= h($m['name']) ?></strong></td>
      <td class="r"><?= $s['count'] ?></td>
      <td class="r"><?= $s['unsoldCount'] ?></td>
      <td class="r"><?= fmt($s['grossSales']) ?></td>
      <td class="r">−<?= fmt($s['totalDed']) ?></td>
      <td class="r">−<?= fmt($s['commissionTotal']) ?></td>
      <td class="r net-cell"><?= fmt($s['netPayout']) ?></td>
    </tr>
  <?php endforeach; ?>
    <tr class="totals-row-final">
      <td></td>
      <td><strong>TOTALS</strong></td>
      <td class="r"><strong><?= $grandSold ?></strong></td>
      <td class="r"><strong><?= $grandUnsold ?></strong></td>
      <td class="r"><strong><?= fmt($grandGross) ?></strong></td>
      <td class="r"><strong>−<?= fmt($grandTotalDed) ?></strong></td>
      <td class="r"></td>
      <td class="r net-cell"><strong><?= fmt($grandPayout) ?></strong></td>
    </tr>
  </tbody>
</table>

<!-- Detailed Vehicles Per Member -->
<?php foreach ($members as $m):
  $mv = array_values(array_filter($allVehicles, fn($v) => (int)$v['member_id'] === (int)$m['id']));
  if (empty($mv)) continue;
?>
  <div class="member-section-hdr">👤 <?= h($m['name']) ?> — <?= count($mv) ?> vehicle<?= count($mv) > 1 ? 's' : '' ?></div>
  <table class="summary-table">
    <thead>
      <tr>
        <th>Lot #</th>
        <th>Make / Model</th>
        <th>Status</th>
        <th class="r">Sold Price</th>
        <th class="r">Tax 10%</th>
        <th class="r">Recycle</th>
        <th class="r">Listing</th>
        <th class="r">Sold Fee</th>
        <th class="r">Nagare</th>
        <th class="r">Net</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($mv as $v):
      $isSold = $v['sold'];
      $net = $isSold
        ? (float)$v['sold_price'] + round((float)$v['sold_price'] * 0.10) + (float)($v['recycle_fee'] ?? 0) - (float)($v['listing_fee'] ?? 0) - (float)($v['sold_fee'] ?? 0)
        : -(float)($v['nagare_fee'] ?? 0);
    ?>
      <tr>
        <td><?= h($v['lot'] ?: '—') ?></td>
        <td><?= h($v['make'] . ' ' . $v['model']) ?></td>
        <td><?= $isSold ? '<span style="color:#1a7a3a;font-weight:600">✓ SOLD</span>' : '<span style="color:#cc3333">✗ UNSOLD</span>' ?></td>
        <td class="r"><?= $isSold ? fmt((float)$v['sold_price']) : '—' ?></td>
        <td class="r"><?= $isSold ? fmt(round((float)$v['sold_price'] * 0.10)) : '—' ?></td>
        <td class="r"><?= $isSold ? fmt((float)($v['recycle_fee'] ?? 0)) : '—' ?></td>
        <td class="r"><?= $isSold ? '−' . fmt((float)($v['listing_fee'] ?? 0)) : '—' ?></td>
        <td class="r"><?= $isSold ? '−' . fmt((float)($v['sold_fee'] ?? 0)) : '—' ?></td>
        <td class="r"><?= !$isSold ? '−' . fmt((float)($v['nagare_fee'] ?? 0)) : '—' ?></td>
        <td class="r net-cell"><?= fmt($net) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endforeach; ?>

<!-- Footer -->
<div style="text-align:center;font-size:10px;color:#bbb;border-top:1px solid #eee;padding-top:12px;margin-top:28px">
  Generated by AuctionKai Settlement System · <?= h($auction['name']) ?> · <?= h($auction['date']) ?> · Designed & Developed by Mirai Global Solutions
</div>

</div>
</body>
</html>

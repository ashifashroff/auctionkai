<?php
require_once 'config.php';

function fmt(float $n): string { return '¥' . number_format(round($n)); }
function h(string $s): string  { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function calcStatement(int $memberId, array $vehicles, array $feeItems): array {
    $mv          = array_values(array_filter($vehicles, fn($v) => (int)$v['member_id'] === $memberId && $v['sold']));
    $count       = count($mv);
    $grossSales  = array_sum(array_column($mv, 'sold_price'));

    $deductions = [];
    $totalDed   = 0;
    foreach ($feeItems as $f) {
        $amt = 0;
        if ($f['type'] === 'flat') {
            $amt = (float)$f['amount'] * $count;
        } elseif ($f['type'] === 'percent') {
            $amt = $grossSales * (float)$f['amount'] / 100;
        } elseif ($f['type'] === 'per_vehicle') {
            $allMv = array_filter($vehicles, fn($v) => (int)$v['member_id'] === $memberId);
            $amt = (float)$f['amount'] * count($allMv);
        }
        $deductions[] = ['name' => $f['name'], 'type' => $f['type'], 'rate' => (float)$f['amount'], 'amount' => $amt];
        $totalDed += $amt;
    }

    $netPayout = $grossSales - $totalDed;
    return compact('mv','count','grossSales','deductions','totalDed','netPayout');
}

$db = db();

// Determine auction
$activeAuctionId = isset($_GET['auction_id']) ? (int)$_GET['auction_id'] : 0;
if (!$activeAuctionId) {
    $first = $db->query("SELECT id FROM auction ORDER BY id LIMIT 1")->fetch();
    $activeAuctionId = $first ? (int)$first['id'] : 0;
}

$auction  = $activeAuctionId ? $db->query("SELECT * FROM auction WHERE id=" . (int)$activeAuctionId)->fetch() : null;
$feeItems = $activeAuctionId ? $db->query("SELECT * FROM fee_items WHERE auction_id=" . (int)$activeAuctionId . " ORDER BY sort_order, id")->fetchAll() : [];
$members  = $activeAuctionId ? $db->query("SELECT * FROM members m WHERE m.user_id=" . (int)($auction['user_id'] ?? 0) . " ORDER BY m.id")->fetchAll() : [];
$vehicles = $activeAuctionId ? $db->query("SELECT v.* FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.auction_id=" . (int)$activeAuctionId . " ORDER BY v.id")->fetchAll() : [];

$printAll = isset($_GET['all']);
$memberId = isset($_GET['member']) ? (int)$_GET['member'] : null;

$targets = $printAll
    ? $members
    : array_values(array_filter($members, fn($m) => (int)$m['id'] === $memberId));

if (empty($targets)) { echo 'No members found.'; exit; }

function renderStatement(array $m, array $s, array $feeItems, array $auction): string {
    $rows = '';
    foreach ($s['mv'] as $v) {
        $rows .= "<tr><td>" . h($v['lot'] ?: '—') . "</td><td>" . h($v['make'] . ' ' . $v['model']) . "</td><td class='r'>" . fmt((float)$v['sold_price']) . "</td></tr>";
    }
    $customRows = '';
    foreach ($s['deductions'] as $d) {
        $label = h($d['name']);
        if ($d['type'] === 'flat') $label .= ' ×' . $s['count'];
        elseif ($d['type'] === 'percent') $label .= ' (' . $d['rate'] . '%)';
        $customRows .= "<div class='row dim'><span>" . $label . "</span><span>−" . fmt($d['amount']) . "</span></div>";
    }
    $loc = !empty($auction['location']) ? ' · ' . h($auction['location']) : '';
    return "
    <div class='page'>
      <div class='hdr'>
        <div><div class='brand'>Auction<span>Kai</span> 精算書</div><div class='sub'>Settlement Statement · " . h($auction['name']) . $loc . "</div></div>
        <div class='meta'><strong>" . h($m['name']) . "</strong>" . h($m['phone']) . "<br>" . h($m['email']) . "<br><br>Date: " . h($auction['date']) . "</div>
      </div>
      <div class='sec'>Sold Vehicles ({$s['count']} units)</div>
      <table>
        <thead><tr><th>Lot #</th><th>Vehicle</th><th class='r'>Sold Price</th></tr></thead>
        <tbody>{$rows}<tr class='tr'><td colspan='3'>Gross Sales Total</td><td class='r'>" . fmt($s['grossSales']) . "</td></tr></tbody>
      </table>
      <div class='sec'>Fee Breakdown</div>
      <div class='fees'>
        <div class='row'><span>Gross Sales</span><span>" . fmt($s['grossSales']) . "</span></div>
        {$customRows}
        <div class='row total'><span>Total Deductions</span><span>−" . fmt($s['totalDed']) . "</span></div>
      </div>
      <div class='net'><div class='net-l'>NET PAYOUT / お支払い額</div><div class='net-n'>" . fmt($s['netPayout']) . "</div></div>
      <div class='footer'>" . h($auction['name']) . " · " . h($auction['date']) . $loc . " · AuctionKai Settlement System</div>
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
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans JP',sans-serif;background:#f4f4f4;color:#111;font-size:13px;line-height:1.5}
.page{background:#fff;width:210mm;min-height:297mm;margin:0 auto 20px;padding:40px 44px;box-shadow:0 4px 24px rgba(0,0,0,.15)}
.hdr{display:flex;justify-content:space-between;align-items:flex-end;border-bottom:3px solid #111;padding-bottom:16px;margin-bottom:28px}
.brand{font-size:26px;font-weight:700;letter-spacing:-.5px} .brand span{color:#B8912A}
.sub{font-size:11px;color:#666;margin-top:3px}
.meta{text-align:right;font-size:12px;color:#444} .meta strong{font-size:16px;color:#111;display:block;margin-bottom:2px}
.sec{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#999;margin-bottom:8px;padding-bottom:5px;border-bottom:1px solid #eee}
table{width:100%;border-collapse:collapse;margin-bottom:28px;font-size:12px}
th{background:#f5f5f5;padding:7px 10px;text-align:left;font-size:10px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:#555}
td{padding:8px 10px;border-bottom:1px solid #f0f0f0} .r{text-align:right;font-family:'Space Mono',monospace}
.tr{font-weight:700;background:#f5f5f5}
.fees{background:#fafafa;border:1px solid #e8e8e8;border-radius:6px;padding:18px;margin-bottom:24px}
.row{display:flex;justify-content:space-between;padding:5px 0;font-size:13px;font-family:'Space Mono',monospace}
.row.dim{color:#777;font-size:12px} .row.sep{border-top:1px dashed #ddd;margin-top:6px;padding-top:10px}
.row.total{border-top:2px solid #ccc;margin-top:6px;padding-top:10px;font-weight:700}
.net{background:#111;color:#fff;padding:18px 22px;border-radius:6px;display:flex;justify-content:space-between;align-items:center;margin-bottom:28px}
.net-l{font-size:13px;font-weight:500;letter-spacing:.5px}
.net-n{font-size:28px;font-weight:700;font-family:'Space Mono',monospace;letter-spacing:-1px}
.footer{text-align:center;font-size:10px;color:#bbb;border-top:1px solid #eee;padding-top:12px}
.ctrl{background:#0A1420;border-bottom:1px solid #1E3A5F;padding:14px 28px;display:flex;gap:12px;align-items:center;position:sticky;top:0;z-index:100}
.ctrl span{color:#D4A84B;font-family:'Space Mono',monospace;font-size:14px;font-weight:700}
.ctrl a{color:#7A94A8;font-size:13px}
.bp{background:#D4A84B;color:#0A1420;border:none;border-radius:8px;padding:9px 22px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit}
.ba{background:#1E3A5F;color:#D4A84B;border:1px solid #2E5A8F;border-radius:8px;padding:9px 16px;font-weight:600;font-size:13px;text-decoration:none;font-family:inherit}
@media print{.ctrl{display:none}body{background:#fff}.page{margin:0;box-shadow:none;width:100%;min-height:auto;page-break-after:always}.page:last-child{page-break-after:auto}}
</style>
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
    $s = calcStatement((int)$m['id'], $vehicles, $feeItems);
    if ($s['count'] === 0) continue;
    echo renderStatement($m, $s, $feeItems, $auction);
endforeach; ?>

<script>
<?php if (!$printAll && $memberId): ?>
window.addEventListener('load', () => setTimeout(() => window.print(), 800));
<?php endif; ?>
</script>
</body>
</html>

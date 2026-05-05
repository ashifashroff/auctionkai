<?php
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
require_once 'includes/db.php';
require_once 'includes/helpers.php';
require_once 'includes/branding.php';

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
 http_response_code(404);
 die('Invalid link');
}

$db = db();
$brand = loadBranding($db);
$brandName = $brand['brand_name'] ?? 'AuctionKai';
$accentColor = sanitizeColor(
 $brand['brand_accent_color'] ?? '#D4A84B'
);

// ── Fetch link record ─────────────────────────
$stmt = $db->prepare("
 SELECT sl.*, 
 m.name as member_name,
 m.phone as member_phone,
 m.email as member_email,
 a.name as auction_name,
 a.date as auction_date,
 a.commission_fee
 FROM statement_links sl
 JOIN members m ON sl.member_id = m.id
 JOIN auction a ON sl.auction_id = a.id
 WHERE sl.token = ?
 LIMIT 1
");
$stmt->execute([$token]);
$link = $stmt->fetch();

// ── Validate link ─────────────────────────────
if (!$link) {
 http_response_code(404);
 ?>
 <!DOCTYPE html>
 <html lang="en">
 <head>
 <meta charset="UTF-8">
 <title>Link Not Found</title>
 <style>
 body{font-family:sans-serif;background:#0A1420;
 color:#E8DCC8;display:flex;align-items:center;
 justify-content:center;min-height:100vh;
 text-align:center;padding:20px}
 .card{background:#111E2D;border:1px solid #1E3A5F;
 border-radius:16px;padding:48px;max-width:400px}
 h1{color:#CC7777;font-size:24px;margin-bottom:12px}
 p{color:#6A88A0;font-size:14px;line-height:1.6}
 </style>
 </head>
 <body>
 <div class="card">
 <div style="font-size:48px;margin-bottom:16px">🔗</div>
 <h1>Link Not Found</h1>
 <p>This statement link is invalid or does not exist.</p>
 </div>
 </body>
 </html>
 <?php
 exit;
}

// Check expiry
if (strtotime($link['expires_at']) < time()) {
 http_response_code(410);
 ?>
 <!DOCTYPE html>
 <html lang="en">
 <head>
 <meta charset="UTF-8">
 <title>Link Expired</title>
 <style>
 body{font-family:sans-serif;background:#0A1420;
 color:#E8DCC8;display:flex;align-items:center;
 justify-content:center;min-height:100vh;
 text-align:center;padding:20px}
 .card{background:#111E2D;border:1px solid #1E3A5F;
 border-radius:16px;padding:48px;max-width:400px}
 h1{color:#D4A84B;font-size:24px;margin-bottom:12px}
 p{color:#6A88A0;font-size:14px;line-height:1.6}
 </style>
 </head>
 <body>
 <div class="card">
 <div style="font-size:48px;margin-bottom:16px">⏱</div>
 <h1>Link Expired</h1>
 <p>This statement link expired on<br>
 <strong style="color:#E8DCC8"><?= date('Y-m-d', strtotime($link['expires_at'])) ?></strong>
 </p>
 <p style="margin-top:12px">Please contact your auction house operator for a new link.</p>
 </div>
 </body>
 </html>
 <?php
 exit;
}

// ── PIN verification ──────────────────────────
$pinVerified = false;
$pinError = '';
$pinAttemptKey = 'pin_attempts_' . md5($token);
$ipPinKey = 'pin_ip_' . md5($token . '_' . trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '')[0]));
$maxAttempts = 5;

if (session_status() === PHP_SESSION_NONE) session_start();
// Regenerate session ID after successful PIN to prevent fixation
$pinJustVerified = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {

 $attempts = (int)($_SESSION[$pinAttemptKey] ?? 0) + (int)($_SESSION[$ipPinKey] ?? 0);

 if ($attempts >= $maxAttempts) {
 $pinError = 'Too many incorrect attempts. Please try again later.';
 } else {
 $enteredPin = trim($_POST['pin'] ?? '');
 if ($enteredPin === $link['pin']) {
 $pinVerified = true;
 $_SESSION[$pinAttemptKey] = 0;
 $_SESSION[$ipPinKey] = 0;
 $_SESSION['verified_token_' . md5($token)] = true;
 $pinJustVerified = true;
 session_regenerate_id(true);

 // Update view count
 $db->prepare("
 UPDATE statement_links 
 SET views = views + 1,
 last_viewed = NOW()
 WHERE token = ?
 ")->execute([$token]);

 } else {
 $_SESSION[$pinAttemptKey] = $attempts + 1;
 $_SESSION[$ipPinKey] = ($_SESSION[$ipPinKey] ?? 0) + 1;
 $remaining = $maxAttempts - ($attempts + 1);
 $pinError = "Incorrect PIN. {$remaining} attempt(s) remaining.";
 }
 }
} elseif (isset($_SESSION['verified_token_' . md5($token)])) {
 $pinVerified = true;
}

// ── If not verified, show PIN form ────────────
if (!$pinVerified):
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>View Statement — <?= htmlspecialchars($brandName) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans JP',sans-serif;background:#0A1420;color:#E8DCC8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#111E2D;border:1px solid #1E3A5F;border-radius:20px;padding:48px 40px;max-width:420px;width:100%;text-align:center;box-shadow:0 24px 64px rgba(0,0,0,.5)}
.brand{font-size:22px;font-weight:700;color:<?= $accentColor ?>;margin-bottom:4px}
.auction-name{font-size:14px;color:#A8C4D8;margin-bottom:4px}
.member-name{font-size:18px;font-weight:700;color:#F0E4C8;margin-bottom:32px}
.lock-icon{font-size:48px;margin-bottom:20px;display:block}
.hint{font-size:13px;color:#6A88A0;margin-bottom:24px;line-height:1.6}
.pin-inputs{display:flex;gap:12px;justify-content:center;margin-bottom:24px}
.pin-input{width:56px;height:64px;background:#0A1724;border:2px solid #1E3A5F;border-radius:12px;text-align:center;font-size:24px;font-weight:700;font-family:'Space Mono',monospace;color:<?= $accentColor ?>;outline:none;transition:border-color .15s}
.pin-input:focus{border-color:<?= $accentColor ?>}
.btn{background:<?= $accentColor ?>;color:#0A1420;border:none;border-radius:10px;padding:13px 40px;font-size:15px;font-weight:700;cursor:pointer;width:100%;font-family:inherit;transition:opacity .15s}
.btn:hover{opacity:.9}
.error{background:#2B0D0D;border:1px solid #CC7777;border-radius:8px;padding:10px 14px;color:#CC7777;font-size:13px;margin-bottom:20px}
.expires{margin-top:20px;font-size:11px;color:#3A5570}
.divider{border:none;border-top:1px solid #1E3A5F;margin:28px 0}
</style>
</head>
<body>
<div class="card">
 <div class="brand">⚡ <?= htmlspecialchars($brandName) ?></div>
 <div class="auction-name"><?= htmlspecialchars($link['auction_name']) ?> · <?= htmlspecialchars($link['auction_date']) ?></div>
 <hr class="divider">
 <span class="lock-icon">🔐</span>
 <div class="member-name"><?= htmlspecialchars($link['member_name']) ?></div>
 <div class="hint">Enter the last <strong>4 digits</strong> of your registered phone number to view your settlement statement.</div>

 <?php if ($pinError): ?>
 <div class="error">⚠ <?= htmlspecialchars($pinError) ?></div>
 <?php endif; ?>

 <form method="POST" action="statement.php?token=<?= urlencode($token) ?>">
 <div class="pin-inputs">
 <input type="text" name="pin_1" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="pin1">
 <input type="text" name="pin_2" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="pin2">
 <input type="text" name="pin_3" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="pin3">
 <input type="text" name="pin_4" class="pin-input" maxlength="1" inputmode="numeric" pattern="[0-9]" autocomplete="off" id="pin4">
 <input type="hidden" name="pin" id="pinCombined">
 </div>
 <button class="btn" type="submit">Verify PIN →</button>
 </form>

 <div class="expires">🔗 Link expires: <?= date('Y-m-d', strtotime($link['expires_at'])) ?></div>
</div>

<script>
const inputs=[document.getElementById('pin1'),document.getElementById('pin2'),document.getElementById('pin3'),document.getElementById('pin4')];
const combined=document.getElementById('pinCombined');
inputs.forEach((input,i)=>{
 input.addEventListener('input',e=>{
  e.target.value=e.target.value.replace(/\D/g,'');
  if(e.target.value.length===1&&i<inputs.length-1)inputs[i+1].focus();
  combined.value=inputs.map(inp=>inp.value).join('');
 });
 input.addEventListener('keydown',e=>{
  if(e.key==='Backspace'&&!e.target.value&&i>0)inputs[i-1].focus();
 });
 input.addEventListener('paste',e=>{
  const pasted=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');
  if(pasted.length===4){inputs.forEach((inp,idx)=>{inp.value=pasted[idx]||'';});combined.value=pasted.slice(0,4);inputs[3].focus();}
  e.preventDefault();
 });
});
inputs[0].focus();
inputs[3].addEventListener('input',()=>{
 if(inputs.every(inp=>inp.value)){setTimeout(()=>{document.querySelector('form').submit();},200);}
});
</script>
</body>
</html>
<?php
exit;
endif;

// ── Fetch full statement data ─────────────────
$vehicles = $db->prepare("
 SELECT * FROM vehicles
 WHERE auction_id=? AND member_id=?
 ORDER BY lot ASC
");
$vehicles->execute([$link['auction_id'], $link['member_id']]);
$memberVehicles = $vehicles->fetchAll();

$specialFees = $db->prepare("
 SELECT * FROM member_fees
 WHERE auction_id=? AND member_id=?
 ORDER BY created_at ASC
");
$specialFees->execute([$link['auction_id'], $link['member_id']]);
$memberSpecialFees = $specialFees->fetchAll();

$commissionFee = (float)($link['commission_fee'] ?? 0);
$s = calcStatement(
 (int)$link['member_id'],
 $memberVehicles,
 $commissionFee,
 $memberSpecialFees
);

// Expiry info
$daysLeft = ceil((strtotime($link['expires_at']) - time()) / 86400);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Settlement Statement — <?= htmlspecialchars($link['member_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Noto Sans JP',sans-serif;background:#0A1420;color:#E8DCC8;min-height:100vh;font-size:13px;line-height:1.5}
.topbar{background:#07101A;border-bottom:1px solid #1E3A5F;padding:14px 24px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;position:sticky;top:0;z-index:100}
.brand{font-size:18px;font-weight:700;color:<?= $accentColor ?>}
.brand-sub{font-size:11px;color:#5A7A90;margin-top:2px;text-transform:uppercase;letter-spacing:1px}
.btn-print{background:<?= $accentColor ?>;color:#0A1420;border:none;border-radius:8px;padding:9px 20px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:6px}
.btn-print:hover{opacity:.9}
.content{max-width:860px;margin:0 auto;padding:28px 20px}
.expiry-banner{border-radius:10px;padding:10px 16px;font-size:12px;margin-bottom:20px;display:flex;align-items:center;gap:8px}
.expiry-ok{background:#1A3A1A;border:1px solid #2A5A2A;color:#4CAF82}
.expiry-warn{background:#2B200D;border:1px solid #D4A84B40;color:#D4A84B}
.stmt-card{background:#111E2D;border:1px solid #1E3A5F;border-radius:16px;overflow:hidden;margin-bottom:20px}
.stmt-header{padding:24px;border-bottom:1px solid #1E3A5F;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px}
.stmt-name{font-size:20px;font-weight:700;color:#F0E4C8}
.stmt-meta{font-size:12px;color:#6A88A0;margin-top:4px}
.stmt-auction{text-align:right;font-size:12px;color:#6A88A0}
.stmt-auction strong{font-size:14px;color:#A8C4D8;display:block;margin-bottom:2px}
.section-title{font-size:10px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#5A7A90;padding:16px 24px 10px;border-bottom:1px solid #1E3A5F}
table{width:100%;border-collapse:collapse}
th{padding:10px 16px;text-align:left;font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#5A7A90;border-bottom:1px solid #1E3A5F}
th.r{text-align:right}
td{padding:10px 16px;border-bottom:1px solid #131F2E;font-size:13px}
td.mono{font-family:'Space Mono',monospace;text-align:right}
.lot-badge{background:#1E3A5F;color:<?= $accentColor ?>;padding:2px 8px;border-radius:4px;font-family:'Space Mono',monospace;font-size:11px}
.sold-badge{background:#1A3A2A;color:#4CAF82;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700}
.unsold-badge{background:#3A1A1A;color:#CC7777;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700}
.fees-section{padding:20px 24px}
.fee-row{display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid #131F2E;font-size:13px}
.fee-row:last-child{border:none}
.fee-label{color:#7A94A8}
.fee-label.dim{color:#4A6480}
.fee-amount{font-family:'Space Mono',monospace;font-weight:600}
.fee-deduction{color:#CC7777}
.fee-addition{color:#4CAF82}
.fee-neutral{color:#E8DCC8}
.fee-divider{border:none;border-top:1px dashed #1E3A5F;margin:8px 0}
.fee-total{display:flex;justify-content:space-between;padding:10px 0 4px;font-weight:700}
.fee-total-label{color:#CC7777;font-size:13px}
.fee-total-amount{font-family:'Space Mono',monospace;color:#CC7777;font-size:14px}
.net-payout{margin:0 24px 24px;background:<?= $accentColor ?>;border-radius:12px;padding:18px 24px;display:flex;justify-content:space-between;align-items:center}
.net-label{font-size:13px;font-weight:700;color:#0A1420;letter-spacing:0.5px}
.net-amount{font-size:28px;font-weight:700;font-family:'Space Mono',monospace;color:#0A1420;letter-spacing:-1px}
.views-info{text-align:center;font-size:11px;color:#3A5570;padding:12px;border-top:1px solid #1E3A5F}
.page-footer{text-align:center;padding:24px;font-size:11px;color:#3A5570;border-top:1px solid #1E3A5F;margin-top:24px}
.page-footer b{color:#5A7A90}
@media print{
 body{background:#fff;color:#111}
 .topbar,.expiry-banner,.views-info,.page-footer{display:none}
 .stmt-card{background:#fff;border:1px solid #ddd;border-radius:0}
 .stmt-name{color:#111}
 .stmt-meta,.stmt-auction{color:#555}
 .section-title{color:#999}
 th{color:#555}
 td{border-color:#f0f0f0}
 .lot-badge{background:#f5f5f5;color:#B8912A}
 .fee-label{color:#555}
 .fee-label.dim{color:#999}
 .fee-deduction{color:#CC3333}
 .fee-addition{color:#2E7D52}
 .fee-neutral{color:#111}
 .fee-total-label,.fee-total-amount{color:#CC3333}
 .net-payout{background:<?= $accentColor ?>;-webkit-print-color-adjust:exact;print-color-adjust:exact}
 @page{size:A4;margin:15mm}
}
@media(max-width:600px){
 .stmt-header{flex-direction:column}
 .stmt-auction{text-align:left}
 .net-amount{font-size:22px}
 table{font-size:12px}
 th,td{padding:8px 12px}
}
</style>
</head>
<body>

<div class="topbar">
 <div>
 <div class="brand">⚡ <?= htmlspecialchars($brandName) ?></div>
 <div class="brand-sub">Settlement Statement · Online View</div>
 </div>
 <button class="btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
</div>

<div class="content">

 <div class="expiry-banner <?= $daysLeft <= 3 ? 'expiry-warn' : 'expiry-ok' ?>">
 <?= $daysLeft <= 3 ? '⚠' : '🔗' ?>
 This link is valid until <strong><?= date('Y-m-d', strtotime($link['expires_at'])) ?></strong> (<?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?> remaining)
 </div>

 <div class="stmt-card">
 <div class="stmt-header">
 <div>
 <div class="stmt-name"><?= htmlspecialchars($link['member_name']) ?></div>
 <div class="stmt-meta">
 <?= htmlspecialchars($link['member_phone'] ?? '') ?>
 <?php if (!empty($link['member_email'])): ?>
 · <?= htmlspecialchars($link['member_email']) ?>
 <?php endif; ?>
 </div>
 </div>
 <div class="stmt-auction">
 <strong><?= htmlspecialchars($link['auction_name']) ?></strong>
 📅 <?= htmlspecialchars($link['auction_date']) ?>
 </div>
 </div>

 <div class="section-title">🚗 Vehicles (<?= count($memberVehicles) ?>)</div>
 <table>
 <thead>
 <tr>
 <th>Lot #</th>
 <th>Vehicle</th>
 <th>Status</th>
 <th class="r">Sold Price</th>
 <th class="r">Tax 10%</th>
 <th class="r">Recycle</th>
 </tr>
 </thead>
 <tbody>
 <?php foreach ($memberVehicles as $v): 
 $tax = round((float)$v['sold_price'] * 0.10);
 ?>
 <tr>
 <td><span class="lot-badge"><?= htmlspecialchars($v['lot'] ?: '—') ?></span></td>
 <td style="color:#A8C4D8">
 <?= htmlspecialchars($v['make'] . ' ' . $v['model']) ?>
 <?php if (!empty($v['year'])): ?>
 <span style="color:#5A7A90"><?= htmlspecialchars($v['year']) ?></span>
 <?php endif; ?>
 </td>
 <td><span class="<?= $v['sold'] ? 'sold-badge' : 'unsold-badge' ?>"><?= $v['sold'] ? '✓ Sold' : '✗ Unsold' ?></span></td>
 <td class="mono" style="color:<?= $v['sold'] ? '#4CAF82' : '#5A7A90' ?>"><?= $v['sold'] ? '¥' . number_format((float)$v['sold_price']) : '—' ?></td>
 <td class="mono" style="color:#6A88A0"><?= $v['sold'] ? '¥' . number_format($tax) : '—' ?></td>
 <td class="mono" style="color:#6A88A0"><?= (float)($v['recycle_fee'] ?? 0) > 0 ? '¥' . number_format((float)$v['recycle_fee']) : '—' ?></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>

 <div class="section-title">💰 Fee Breakdown</div>
 <div class="fees-section">
 <div class="fee-row">
 <span class="fee-label">Gross Sales</span>
 <span class="fee-amount fee-neutral">¥<?= number_format($s['grossSales']) ?></span>
 </div>
 <div class="fee-row">
 <span class="fee-label">＋ Tax (10%)</span>
 <span class="fee-amount fee-addition">＋¥<?= number_format($s['taxTotal'] ?? 0) ?></span>
 </div>
 <?php if (($s['recycleTotal'] ?? 0) > 0): ?>
 <div class="fee-row">
 <span class="fee-label">＋ Recycle Fee</span>
 <span class="fee-amount fee-addition">＋¥<?= number_format($s['recycleTotal']) ?></span>
 </div>
 <?php endif; ?>
 <hr class="fee-divider">
 <div class="fee-row">
 <span class="fee-label" style="font-weight:600;color:#E8DCC8">Total Received</span>
 <span class="fee-amount" style="color:#E8DCC8;font-size:14px">¥<?= number_format($s['totalReceived'] ?? $s['grossSales']) ?></span>
 </div>
 <hr class="fee-divider">
 <?php if (($s['listingFeeTotal']??0) > 0): ?>
 <div class="fee-row">
 <span class="fee-label dim">－ Listing Fee</span>
 <span class="fee-amount fee-deduction">－¥<?= number_format($s['listingFeeTotal']) ?></span>
 </div>
 <?php endif; ?>
 <?php if (($s['soldFeeTotal']??0) > 0): ?>
 <div class="fee-row">
 <span class="fee-label dim">－ Sold Fee (落札手数料)</span>
 <span class="fee-amount fee-deduction">－¥<?= number_format($s['soldFeeTotal']) ?></span>
 </div>
 <?php endif; ?>
 <?php if (($s['nagareFeeTotal']??0) > 0): ?>
 <div class="fee-row">
 <span class="fee-label dim">－ Nagare Fee (流れ費用)</span>
 <span class="fee-amount fee-deduction">－¥<?= number_format($s['nagareFeeTotal']) ?></span>
 </div>
 <?php endif; ?>
 <?php if (($s['otherFeeTotal']??0) > 0): ?>
 <div class="fee-row">
 <span class="fee-label dim">－ Other Fee</span>
 <span class="fee-amount fee-deduction">－¥<?= number_format($s['otherFeeTotal']) ?></span>
 </div>
 <?php endif; ?>
 <?php if (($s['commissionTotal']??0) > 0): ?>
 <div class="fee-row">
 <span class="fee-label dim">－ Commission</span>
 <span class="fee-amount fee-deduction">－¥<?= number_format($s['commissionTotal']) ?></span>
 </div>
 <?php endif; ?>
 <?php foreach ($memberSpecialFees as $sf): 
 $isAdd = $sf['fee_type'] === 'addition';
 ?>
 <div class="fee-row">
 <span class="fee-label dim">
 <?= $isAdd ? '＋' : '－' ?>
 <?= htmlspecialchars($sf['fee_name']) ?>
 <?php if (!empty($sf['notes'])): ?>
 <span style="font-size:11px;color:#3A5570">(<?= htmlspecialchars($sf['notes']) ?>)</span>
 <?php endif; ?>
 </span>
 <span class="fee-amount <?= $isAdd ? 'fee-addition' : 'fee-deduction' ?>">
 <?= $isAdd ? '＋' : '－' ?>¥<?= number_format((float)$sf['amount']) ?>
 </span>
 </div>
 <?php endforeach; ?>
 <hr class="fee-divider">
 <div class="fee-total">
 <span class="fee-total-label">Total Deductions</span>
 <span class="fee-total-amount">－¥<?= number_format($s['totalDed']) ?></span>
 </div>
 </div>

 <div class="net-payout">
 <div>
 <div class="net-label">NET PAYOUT</div>
 <div class="net-label">お支払い額</div>
 </div>
 <div class="net-amount">¥<?= number_format($s['netPayout']) ?></div>
 </div>

 <div class="views-info">
 👁 Viewed <?= (int)$link['views'] ?> time<?= $link['views'] != 1 ? 's' : '' ?>
 · Last viewed: <?= $link['last_viewed'] ? date('Y-m-d H:i', strtotime($link['last_viewed'])) : 'Just now' ?>
 </div>
 </div>

 <div class="page-footer">
 <?= htmlspecialchars($link['auction_name']) ?> · <?= htmlspecialchars($link['auction_date']) ?> · <?= htmlspecialchars($brand['brand_footer_text'] ?? 'Mirai Global Solutions') ?>
 </div>

</div>
</body>
</html>

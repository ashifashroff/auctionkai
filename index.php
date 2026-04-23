<?php
require_once 'config.php';
session_start();

// ─── AUTH CHECK ────────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if (empty($_SESSION['tok'])) $_SESSION['tok'] = bin2hex(random_bytes(16));
$tok = $_SESSION['tok'];
$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'user';

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function fmt(float $n): string {
    return '¥' . number_format(round($n));
}
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function postForm(string $action, string $tabTarget, string $tok): string {
    return "<form method='POST' action='index.php'>"
         . "<input type='hidden' name='action' value='" . h($action) . "'>"
         . "<input type='hidden' name='tab'    value='" . h($tabTarget) . "'>"
         . "<input type='hidden' name='_tok'   value='" . h($tok) . "'>";
}

// ─── LOAD DB ─────────────────────────────────────────────────────────────────
$db = db();

// ─── ACTIVE AUCTION (selected via navbar or session) ─────────────────────────
$allAuctions = $db->query("SELECT * FROM auction WHERE user_id=$userId ORDER BY date DESC, id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['auction_id'])) {
    $_SESSION['auction_id'] = (int)$_GET['auction_id'];
}
if (empty($_SESSION['auction_id']) && !empty($allAuctions)) {
    $_SESSION['auction_id'] = (int)$allAuctions[0]['id'];
}
$activeAuctionId = (int)($_SESSION['auction_id'] ?? 0);
$auction = null;
if ($activeAuctionId) {
    $stmt = $db->prepare("SELECT * FROM auction WHERE id=? AND user_id=?");
    $stmt->execute([$activeAuctionId, $userId]);
    $auction = $stmt->fetch();
}
if (!$auction && !empty($allAuctions)) {
    $auction = $allAuctions[0];
    $activeAuctionId = (int)$auction['id'];
    $_SESSION['auction_id'] = $activeAuctionId;
}

// ─── HANDLE POSTS ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['_tok'] ?? '') !== $tok) {
        http_response_code(403); exit('Forbidden');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add_auction') {
        $name = trim($_POST['name'] ?? '');
        $date = trim($_POST['date'] ?? '');
        $location = trim($_POST['location'] ?? '');
        if ($name !== '' && $date !== '') {
            $stmt = $db->prepare("INSERT INTO auction (user_id, name, date, location) VALUES (?,?,?,?)");
            $stmt->execute([$userId, $name, $date, $location]);
            $newId = (int)$db->lastInsertId();
            // Create default fees for new auction
            $db->prepare("INSERT INTO fees (auction_id, entry_fee, commission_rate, tax_rate, transport_fee) VALUES (?,?,?,?,?)")
               ->execute([$newId, 3000, 3.00, 10.00, 5000]);
            $_SESSION['auction_id'] = $newId;
        }
    }

    elseif ($action === 'delete_auction') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM auction WHERE id=? AND user_id=?")->execute([$id, $userId]);
        unset($_SESSION['auction_id']);
    }

    elseif ($action === 'save_auction') {
        $stmt = $db->prepare("UPDATE auction SET name=?, date=?, location=? WHERE id=? AND user_id=?");
        $stmt->execute([trim($_POST['name']), trim($_POST['date']), trim($_POST['location'] ?? ''), $activeAuctionId, $userId]);
    }

    elseif ($action === 'add_member') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $db->prepare("INSERT INTO members (user_id, name, phone, email) VALUES (?,?,?,?)");
            $stmt->execute([$userId, $name, trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? '')]);
        }
    }

    elseif ($action === 'remove_member') {
        $stmt = $db->prepare("DELETE FROM members WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
    }

    elseif ($action === 'add_vehicle') {
        $memberId = (int)($_POST['memberId'] ?? 0);
        $make     = trim($_POST['make'] ?? '');
        if ($memberId && $make !== '') {
            $stmt = $db->prepare("INSERT INTO vehicles (auction_id, member_id, make, model, year, lot, sold_price, sold) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $activeAuctionId, $memberId, $make,
                trim($_POST['model']    ?? ''),
                trim($_POST['year']     ?? ''),
                trim($_POST['lot']      ?? ''),
                (float)($_POST['soldPrice'] ?? 0),
                isset($_POST['sold']) ? 1 : 0,
            ]);
        }
    }

    elseif ($action === 'remove_vehicle') {
        $stmt = $db->prepare("DELETE FROM vehicles WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
    }

    elseif ($action === 'toggle_sold') {
        $stmt = $db->prepare("UPDATE vehicles SET sold = NOT sold WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
    }

    elseif ($action === 'save_fees') {
        $stmt = $db->prepare("UPDATE fees SET entry_fee=?, commission_rate=?, tax_rate=?, transport_fee=? WHERE auction_id=?");
        $stmt->execute([
            (float)($_POST['entryFee']       ?? 0),
            (float)($_POST['commissionRate'] ?? 0),
            (float)($_POST['taxRate']        ?? 0),
            (float)($_POST['transportFee']   ?? 0),
            $activeAuctionId,
        ]);
    }

    elseif ($action === 'add_custom') {
        $cname   = trim($_POST['customName']   ?? '');
        $camount = (float)($_POST['customAmount'] ?? 0);
        if ($cname !== '' && $camount > 0) {
            $stmt = $db->prepare("INSERT INTO custom_deductions (auction_id, name, amount) VALUES (?,?,?)");
            $stmt->execute([$activeAuctionId, $cname, $camount]);
        }
    }

    elseif ($action === 'remove_custom') {
        $stmt = $db->prepare("DELETE FROM custom_deductions WHERE id=?");
        $stmt->execute([(int)$_POST['id']]);
    }

    $tab = $_POST['tab'] ?? 'members';
    header("Location: index.php?tab=$tab");
    exit;
}

// ─── FETCH DATA (filtered by active auction) ─────────────────────────────────
$fees     = $activeAuctionId
    ? ($db->query("SELECT * FROM fees WHERE auction_id=" . (int)$activeAuctionId . " ORDER BY id LIMIT 1")->fetch() ?: null)
    : null;
if (!$fees && $activeAuctionId) {
    // Auto-create fees if missing
    $db->prepare("INSERT INTO fees (auction_id, entry_fee, commission_rate, tax_rate, transport_fee) VALUES (?,?,?,?,?)")
       ->execute([$activeAuctionId, 3000, 3.00, 10.00, 5000]);
    $fees = $db->query("SELECT * FROM fees WHERE auction_id=" . (int)$activeAuctionId . " ORDER BY id LIMIT 1")->fetch();
}
$customs  = $activeAuctionId
    ? $db->query("SELECT * FROM custom_deductions WHERE auction_id=" . (int)$activeAuctionId . " ORDER BY id")->fetchAll()
    : [];
$members  = $userId
    ? $db->query("SELECT * FROM members WHERE user_id=$userId ORDER BY id")->fetchAll()
    : [];
$vehicles = $activeAuctionId
    ? $db->query("SELECT v.* FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.auction_id=" . (int)$activeAuctionId . " AND m.user_id=$userId ORDER BY v.id")->fetchAll()
    : [];

$fees['customDeductions'] = $customs;

// ─── CALC STATEMENT ──────────────────────────────────────────────────────────
function calcStatement(int $memberId, array $vehicles, array $fees): array {
    $mv = array_values(array_filter($vehicles, fn($v) => (int)$v['member_id'] === $memberId && $v['sold']));
    $count       = count($mv);
    $grossSales  = array_sum(array_column($mv, 'sold_price'));
    $entryTotal  = $fees['entry_fee'] * $count;
    $commTotal   = array_sum(array_map(fn($v) => $v['sold_price'] * $fees['commission_rate'] / 100, $mv));
    $transTotal  = $fees['transport_fee'] * $count;
    $customSumPer= array_sum(array_column($fees['customDeductions'], 'amount'));
    $customTotal = $customSumPer * $count;
    $subtotal    = $entryTotal + $commTotal + $transTotal + $customTotal;
    $tax         = $subtotal * $fees['tax_rate'] / 100;
    $totalDed    = $subtotal + $tax;
    $netPayout   = $grossSales - $totalDed;
    return compact('mv','count','grossSales','entryTotal','commTotal','transTotal','customTotal','subtotal','tax','totalDed','netPayout');
}

// ─── ACTIVE TAB & STATS ───────────────────────────────────────────────────────
$tab      = $_GET['tab'] ?? 'members';
$tabs     = ['members'=>['icon'=>'👥','label'=>'Members'],'vehicles'=>['icon'=>'🚗','label'=>'Vehicles'],'fees'=>['icon'=>'⚙️','label'=>'Fee Settings'],'statements'=>['icon'=>'📄','label'=>'Statements']];
$totalSold= count(array_filter($vehicles, fn($v) => $v['sold']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AuctionKai — Settlement System</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ─── TOP BAR ─────────────────────────────────────────────────── -->
<div class="topbar">
  <div>
    <div class="brand">⚡ AuctionKai <span class="db-badge">MySQL</span></div>
    <div class="brand-sub">Settlement Management System</div>
  </div>
  <?php if ($auction): ?>
  <div class="auction-edit">
    <?= postForm('save_auction', $tab, $tok) ?>
      <input class="inp" style="width:220px" name="name" value="<?= h($auction['name']) ?>" placeholder="Auction name">
      <input class="inp" type="date" style="width:140px" name="date" value="<?= h($auction['date']) ?>">
      <input class="inp" style="width:180px" name="location" value="<?= h($auction['location'] ?? '') ?>" placeholder="Location">
      <button class="btn btn-dark btn-sm" type="submit">Save</button>
    </form>
  </div>
  <?php endif; ?>
  <div class="user-menu">
    <div class="user-avatar"><?= mb_strtoupper(mb_substr($userName, 0, 1)) ?></div>
    <div>
      <div class="user-name"><?= h($userName) ?></div>
      <div class="user-role"><?= h($userRole) ?></div>
    </div>
    <a href="logout.php" class="logout-btn">Logout</a>
  </div>
</div>


<!-- ─── AUCTION SELECTOR BAR ─────────────────────────────────────── -->
<div class="auction-bar">
  <div class="auction-select">
    <?php foreach ($allAuctions as $a): ?>
      <a class="auction-chip <?= (int)$a['id'] === $activeAuctionId ? 'active' : '' ?>" href="?auction_id=<?= (int)$a['id'] ?>&tab=<?= h($tab) ?>">
        <?= h($a['name']) ?>
        <?php if (!empty($a['location'])): ?><span class="chip-loc">📍 <?= h($a['location']) ?></span><?php endif; ?>
      </a>
    <?php endforeach; ?>
    <button class="auction-add" onclick="document.getElementById('addAuctionForm').style.display=document.getElementById('addAuctionForm').style.display==='none'?'flex':'none'">+ New Auction</button>
  </div>
  <?php if ($auction): ?>
  <div class="auction-meta">
    <b><?= h($auction['name']) ?></b> · <?= h($auction['date']) ?> · <?= h($auction['location'] ?? 'No location') ?>
  </div>
  <?php endif; ?>
</div>

<!-- ─── ADD AUCTION FORM (hidden by default) ─────────────────────── -->
<div id="addAuctionForm" style="display:none;padding:16px 28px;background:var(--bg2);border-bottom:1px solid var(--border)">
  <?= postForm('add_auction', 'members', $tok) ?>
    <div class="add-row ar-auction" style="margin-bottom:0">
      <div><label class="lbl">Auction Name *</label><input class="inp" name="name" placeholder="e.g. Tokyo Bay Auto Auction" required></div>
      <div><label class="lbl">Date *</label><input class="inp" type="date" name="date" required></div>
      <div><label class="lbl">Location</label><input class="inp" name="location" placeholder="e.g. Odaiba, Tokyo"></div>
      <div style="display:flex;align-items:flex-end"><button class="btn btn-gold" type="submit">+ Create</button></div>
    </div>
  </form>
</div>

<!-- ─── TABS ─────────────────────────────────────────────────────── -->
<div class="tabs">
  <?php foreach ($tabs as $key => $t): ?>
    <a class="tab-btn <?= $tab === $key ? 'active' : '' ?>" href="?tab=<?= $key ?><?= $activeAuctionId ? '&auction_id='.$activeAuctionId : '' ?>"><?= $t['icon'] ?> <?= $t['label'] ?></a>
  <?php endforeach; ?>
  <div class="tab-stats">
    <span><b><?= count($members) ?></b> members</span>
    <span><b class="g"><?= $totalSold ?></b> sold / <b><?= count($vehicles) ?></b> total</span>
  </div>
</div>

<!-- ─── CONTENT ───────────────────────────────────────────────────── -->
<div class="content">

<?php if (!$auction): ?>
  <div class="no-auction">
    <h2>No Auctions Yet</h2>
    <p>Click <strong>"+ New Auction"</strong> above to create your first auction.</p>
  </div>

<?php elseif ($tab === 'members'): ?>
<h2>Members / Sellers — <?= h($auction['name']) ?></h2>
<div class="card card-pad" style="margin-bottom:20px">
  <div class="sec-lbl">Add New Member</div>
  <?= postForm('add_member', 'members', $tok) ?>
    <div class="add-row ar-members">
      <div><label class="lbl">Full Name *</label><input class="inp" name="name" placeholder="e.g. Ahmad Hassan" required></div>
      <div><label class="lbl">Phone</label><input class="inp" name="phone" placeholder="090-xxxx-xxxx"></div>
      <div><label class="lbl">Email</label><input class="inp" type="email" name="email" placeholder="email@example.com"></div>
      <div style="display:flex;align-items:flex-end"><button class="btn btn-gold" type="submit">+ Add</button></div>
    </div>
  </form>
</div>
<div style="display:flex;flex-direction:column;gap:10px">
<?php if (empty($members)): ?>
  <div class="card card-pad" style="text-align:center;color:var(--muted);padding:48px">No members yet for this auction.</div>
<?php else: ?>
  <?php foreach ($members as $m):
    $mv        = array_filter($vehicles, fn($v) => (int)$v['member_id'] === (int)$m['id']);
    $soldCount = count(array_filter($mv, fn($v) => $v['sold']));
    $s         = calcStatement((int)$m['id'], $vehicles, $fees);
  ?>
  <div class="card mi">
    <div class="av"><?= mb_strtoupper(mb_substr($m['name'], 0, 1)) ?></div>
    <div style="flex:1;min-width:0">
      <div class="mn"><?= h($m['name']) ?></div>
      <div class="mm"><?= h($m['phone']) ?> · <?= h($m['email']) ?></div>
    </div>
    <div class="ms">
      <div class="ms-big"><?= count($mv) ?></div>
      <div class="ms-sm"><?= $soldCount ?> sold</div>
    </div>
    <div class="mp">
      <div class="mp-num"><?= fmt($s['netPayout']) ?></div>
      <div class="ms-sm">net payout</div>
    </div>
    <?= postForm('remove_member', 'members', $tok) ?>
      <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
      <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('Remove <?= h($m['name']) ?> and all their vehicles?')">Remove</button>
    </form>
  </div>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<?php elseif ($tab === 'vehicles'): ?>
<h2>Vehicle Listings — <?= h($auction['name']) ?></h2>
<div class="card card-pad" style="margin-bottom:20px">
  <div class="sec-lbl">Add Vehicle</div>
  <?= postForm('add_vehicle', 'vehicles', $tok) ?>
    <div class="add-row ar-vehicles">
      <div>
        <label class="lbl">Member *</label>
        <select class="inp" name="memberId" required>
          <option value="">Select member…</option>
          <?php foreach ($members as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= h($m['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div><label class="lbl">Make *</label><input class="inp" name="make" placeholder="Toyota" required></div>
      <div><label class="lbl">Model</label><input class="inp" name="model" placeholder="Prius"></div>
      <div><label class="lbl">Year</label><input class="inp" name="year" placeholder="2020" maxlength="4"></div>
      <div><label class="lbl">Lot #</label><input class="inp" name="lot" placeholder="A-001"></div>
      <div><label class="lbl">Sold Price (¥)</label><input class="inp mono" type="number" name="soldPrice" placeholder="850000" min="0"></div>
      <div style="display:flex;align-items:flex-end;gap:8px">
        <label style="display:flex;align-items:center;gap:5px;color:var(--muted);font-size:12px;cursor:pointer">
          <input type="checkbox" name="sold" checked style="accent-color:var(--gold)"> Sold
        </label>
        <button class="btn btn-gold" type="submit">Add</button>
      </div>
    </div>
  </form>
</div>
<div class="card" style="overflow:hidden">
  <table class="vt">
    <thead><tr><th>Lot #</th><th>Member</th><th>Vehicle</th><th>Year</th><th class="r">Sold Price</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (empty($vehicles)): ?>
      <tr><td colspan="7" style="padding:48px;text-align:center;color:var(--muted)">No vehicles yet for this auction.</td></tr>
    <?php else: ?>
      <?php foreach ($vehicles as $v):
        $owner = array_values(array_filter($members, fn($m) => (int)$m['id'] === (int)$v['member_id']))[0] ?? null;
      ?>
      <tr>
        <td><span class="lot"><?= h($v['lot'] ?: '—') ?></span></td>
        <td><?= h($owner['name'] ?? '?') ?></td>
        <td style="color:var(--text2)"><?= h($v['make'] . ' ' . $v['model']) ?></td>
        <td style="color:var(--muted)"><?= h($v['year'] ?: '—') ?></td>
        <td style="text-align:right;font-family:var(--mono);color:<?= $v['sold'] ? 'var(--green)' : 'var(--muted)' ?>">
          <?= $v['sold'] ? fmt((float)$v['sold_price']) : '—' ?>
        </td>
        <td>
          <?= postForm('toggle_sold', 'vehicles', $tok) ?>
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <button class="sb <?= $v['sold'] ? 'sy' : 'sn' ?>" type="submit"><?= $v['sold'] ? '✓ SOLD' : '✗ UNSOLD' ?></button>
          </form>
        </td>
        <td>
          <?= postForm('remove_vehicle', 'vehicles', $tok) ?>
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <button class="btn-icon" type="submit" onclick="return confirm('Remove this vehicle?')">×</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'fees'): ?>
<div style="max-width:580px">
<h2>Fee Settings — <?= h($auction['name']) ?></h2>
<?= postForm('save_fees', 'fees', $tok) ?>
<div class="card card-pad" style="margin-bottom:16px">
  <div class="sec-lbl">Standard Fees (per vehicle)</div>
  <?php $sf = [
    ['l'=>'Entry / Listing Fee',     'k'=>'entry_fee',       'unit'=>'¥', 'note'=>'Flat fee per listed vehicle'],
    ['l'=>'Sold Commission Rate',     'k'=>'commission_rate', 'unit'=>'%', 'note'=>'% of sold price (落札手数料)'],
    ['l'=>'Consumption Tax Rate',     'k'=>'tax_rate',        'unit'=>'%', 'note'=>'Applied to total fees (消費税)'],
    ['l'=>'Transport / Handling Fee', 'k'=>'transport_fee',   'unit'=>'¥', 'note'=>'Flat fee per vehicle'],
  ]; ?>
  <?php foreach ($sf as $f): ?>
  <div class="fr">
    <div class="fr-lbl"><div class="fr-name"><?= $f['l'] ?></div><div class="fr-note"><?= $f['note'] ?></div></div>
    <div class="fr-inp">
      <span class="fr-unit"><?= $f['unit'] ?></span>
      <input class="inp mono" style="width:110px" type="number" step="any" name="<?= $f['k'] ?>" value="<?= $fees[$f['k']] ?>">
    </div>
  </div>
  <?php endforeach; ?>
  <div style="margin-top:16px;text-align:right"><button class="btn btn-gold" type="submit">Save Fees</button></div>
</div>
</form>

<div class="card card-pad">
  <div class="sec-lbl">Custom Deductions (per vehicle)</div>
  <?php foreach ($fees['customDeductions'] as $d): ?>
  <div class="ci">
    <span class="ci-name"><?= h($d['name']) ?></span>
    <span class="ci-amt">¥<?= number_format((float)$d['amount']) ?></span>
    <?= postForm('remove_custom', 'fees', $tok) ?>
      <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
      <button class="btn-icon" type="submit">×</button>
    </form>
  </div>
  <?php endforeach; ?>
  <?= postForm('add_custom', 'fees', $tok) ?>
    <div class="add-ci">
      <input class="inp" name="customName" placeholder="Deduction name" style="flex:1" required>
      <input class="inp mono" type="number" name="customAmount" placeholder="Amount ¥" style="width:140px" min="1" required>
      <button class="btn btn-dark" type="submit">+ Add</button>
    </div>
  </form>
</div>
</div>

<?php elseif ($tab === 'statements'): ?>
<div class="st-top">
  <h2>Settlement Statements — <?= h($auction['name']) ?></h2>
  <a class="btn btn-dark" href="pdf.php?all=1&auction_id=<?= $activeAuctionId ?>" target="_blank">↓ Print All PDFs</a>
</div>
<?php if (empty($members)): ?>
  <div class="card nm">No members registered for this auction.</div>
<?php else: ?>
  <?php foreach ($members as $m):
    $s = calcStatement((int)$m['id'], $vehicles, $fees);
    $emailSubject = urlencode("Settlement Statement – {$auction['name']} {$auction['date']}");
    $emailBody    = urlencode("Dear {$m['name']},\n\nPlease find your settlement for {$auction['name']} on {$auction['date']}.\n\nVehicles Sold: {$s['count']}\nGross Sales: " . fmt($s['grossSales']) . "\nTotal Deductions: " . fmt($s['totalDed']) . "\n\nNET PAYOUT: " . fmt($s['netPayout']) . "\n\nThank you.");
  ?>
  <div class="card" style="overflow:hidden;margin-bottom:20px">
    <div class="sh">
      <div><div class="sn2"><?= h($m['name']) ?></div><div class="sm"><?= h($m['email']) ?> · <?= h($m['phone']) ?></div></div>
      <div class="sa">
        <a class="btn-email" href="mailto:<?= h($m['email']) ?>?subject=<?= $emailSubject ?>&body=<?= $emailBody ?>">✉ Send Email</a>
        <a class="btn btn-gold btn-sm" href="pdf.php?member=<?= (int)$m['id'] ?>&auction_id=<?= $activeAuctionId ?>" target="_blank">↓ PDF</a>
      </div>
    </div>
    <?php if ($s['count'] === 0): ?>
      <div class="se">No sold vehicles for this member.</div>
    <?php else: ?>
    <div class="sb2">
      <div class="sl">
        <div class="ssl">Sold Vehicles (<?= $s['count'] ?>)</div>
        <?php foreach ($s['mv'] as $v): ?>
        <div class="vr">
          <span class="vr-car"><span class="vr-lot"><?= h($v['lot'] ?: '—') ?></span><?= h($v['make'] . ' ' . $v['model']) ?> <span class="vr-yr"><?= h($v['year']) ?></span></span>
          <span class="vr-p"><?= fmt((float)$v['sold_price']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="sg"><span class="sg-l">Gross Sales</span><span class="sg-n"><?= fmt($s['grossSales']) ?></span></div>
      </div>
      <div class="sr">
        <div class="ssl">Deductions</div>
        <div class="dr"><span class="dr-l">Entry Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['entryTotal']) ?></span></div>
        <div class="dr"><span class="dr-l">Commission <?= $fees['commission_rate'] ?>%</span><span class="dr-a">−<?= fmt($s['commTotal']) ?></span></div>
        <div class="dr"><span class="dr-l">Transport ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['transTotal']) ?></span></div>
        <?php foreach ($fees['customDeductions'] as $d): ?>
        <div class="dr"><span class="dr-l"><?= h($d['name']) ?> ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt((float)$d['amount'] * $s['count']) ?></span></div>
        <?php endforeach; ?>
        <div class="dr"><span class="dr-l dim">Tax <?= $fees['tax_rate'] ?>% on fees</span><span class="dr-a">−<?= fmt($s['tax']) ?></span></div>
        <div class="dt"><span class="dt-l">Total Deductions</span><span class="dt-n">−<?= fmt($s['totalDed']) ?></span></div>
        <div class="np"><span class="np-l">NET PAYOUT / お支払い額</span><span class="np-n"><?= fmt($s['netPayout']) ?></span></div>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>
</div>
</body>
</html>
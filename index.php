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

// ─── AUTO-EXPIRE: Delete sold vehicles and expired auctions ────────────────────
$today = date('Y-m-d');
$expiredAuctions = $db->query("SELECT id FROM auction WHERE user_id=$userId AND expires_at < '$today'")->fetchAll();
foreach ($expiredAuctions as $ea) {
    // Delete sold vehicles for this auction (keep unsold)
    $db->prepare("DELETE FROM vehicles WHERE auction_id=? AND sold=1")->execute([(int)$ea['id']]);
    // Delete vehicle_fees for remaining vehicles (orphan cleanup)
    $db->prepare("DELETE vf FROM vehicle_fees vf LEFT JOIN vehicles v ON vf.vehicle_id = v.id WHERE v.id IS NULL")->execute();
    // Delete the auction itself
    $db->prepare("DELETE FROM auction WHERE id=? AND user_id=?")->execute([(int)$ea['id'], $userId]);
}
// Refresh if current auction was deleted
if ($activeAuctionId && !$db->query("SELECT id FROM auction WHERE id=$activeAuctionId AND user_id=$userId")->fetch()) {
    unset($_SESSION['auction_id']);
    $allAuctions = $db->query("SELECT * FROM auction WHERE user_id=$userId ORDER BY date DESC, id DESC")->fetchAll();
    $auction = !empty($allAuctions) ? $allAuctions[0] : null;
    $activeAuctionId = $auction ? (int)$auction['id'] : 0;
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
        if ($name !== '' && $date !== '') {
            $expiresAt = date('Y-m-d', strtotime($date . ' +14 days'));
            $stmt = $db->prepare("INSERT INTO auction (user_id, name, date, expires_at) VALUES (?,?,?,?)");
            $stmt->execute([$userId, $name, $date, $expiresAt]);
            $newId = (int)$db->lastInsertId();
            $_SESSION['auction_id'] = $newId;
        }
    }

    elseif ($action === 'delete_auction') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM auction WHERE id=? AND user_id=?")->execute([$id, $userId]);
        unset($_SESSION['auction_id']);
    }

    elseif ($action === 'save_auction') {
        $stmt = $db->prepare("UPDATE auction SET name=?, date=?, commission_rate=? WHERE id=? AND user_id=?");
        $stmt->execute([trim($_POST['name']), trim($_POST['date']), (float)($_POST['commissionRate'] ?? 3.00), $activeAuctionId, $userId]);
    }

    elseif ($action === 'add_member') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $db->prepare("INSERT INTO members (user_id, name, phone, email) VALUES (?,?,?,?)");
            $stmt->execute([$userId, $name, trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? '')]);
        }
    }

    elseif ($action === 'update_member') {
        $id   = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $db->prepare("UPDATE members SET name=?, phone=?, email=? WHERE id=? AND user_id=?");
            $stmt->execute([$name, trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''), $id, $userId]);
        }
    }

    elseif ($action === 'remove_member') {
        $stmt = $db->prepare("DELETE FROM members WHERE id=? AND user_id=?");
        $stmt->execute([(int)$_POST['id'], $userId]);
    }

    elseif ($action === 'add_vehicle') {
        $memberId = (int)($_POST['memberId'] ?? 0);
        $make     = trim($_POST['make'] ?? '');
        if ($memberId && $make !== '') {
            $stmt = $db->prepare("INSERT INTO vehicles (auction_id, member_id, make, model, lot, sold_price, recycle_fee, listing_fee, sold_fee, nagare_fee, other_fee, sold) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $activeAuctionId, $memberId, $make,
                trim($_POST['model']    ?? ''),
                trim($_POST['lot']      ?? ''),
                (float)($_POST['soldPrice'] ?? 0),
                (float)($_POST['recycleFee'] ?? 0),
                (float)($_POST['listingFee'] ?? 0),
                (float)($_POST['soldFee'] ?? 0),
                (float)($_POST['nagareFee'] ?? 0),
                (float)($_POST['otherFee'] ?? 0),
                isset($_POST['sold']) ? 1 : 0,
            ]);
        }
    }

    elseif ($action === 'update_vehicle') {
        $id   = (int)$_POST['id'];
        $make = trim($_POST['make'] ?? '');
        if ($make !== '') {
            $stmt = $db->prepare("UPDATE vehicles SET member_id=?, make=?, model=?, lot=?, sold_price=?, recycle_fee=?, listing_fee=?, sold_fee=?, nagare_fee=?, other_fee=?, sold=? WHERE id=?");
            $stmt->execute([
                (int)($_POST['memberId'] ?? 0),
                $make,
                trim($_POST['model'] ?? ''),
                trim($_POST['lot']  ?? ''),
                (float)($_POST['soldPrice'] ?? 0),
                (float)($_POST['recycleFee'] ?? 0),
                (float)($_POST['listingFee'] ?? 0),
                (float)($_POST['soldFee'] ?? 0),
                (float)($_POST['nagareFee'] ?? 0),
                (float)($_POST['otherFee'] ?? 0),
                isset($_POST['sold']) ? 1 : 0,
                $id,
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


    $tab = $_POST['tab'] ?? 'members';
    header("Location: index.php?tab=$tab");
    exit;
}

// ─── FETCH DATA (filtered by active auction) ─────────────────────────────────
$members  = $userId
    ? $db->query("SELECT * FROM members WHERE user_id=$userId ORDER BY id")->fetchAll()
    : [];
$vehicles = $activeAuctionId
    ? $db->query("SELECT v.* FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.auction_id=" . (int)$activeAuctionId . " AND m.user_id=$userId ORDER BY v.id")->fetchAll()
    : [];



// ─── CALC STATEMENT ──────────────────────────────────────────────────────────
function calcStatement(int $memberId, array $vehicles, float $commissionRate): array {
    $mv = array_values(array_filter($vehicles, fn($v) => (int)$v['member_id'] === $memberId && $v['sold']));
    $count       = count($mv);
    $grossSales  = array_sum(array_column($mv, 'sold_price'));
    $taxTotal    = array_sum(array_map(fn($v) => round((float)$v['sold_price'] * 0.10), $mv));
    $recycleTotal= array_sum(array_map(fn($v) => (float)($v['recycle_fee'] ?? 0), $mv));
    $listingFeeTotal = array_sum(array_map(fn($v) => (float)($v['listing_fee'] ?? 0), $mv));
    $soldFeeTotal    = array_sum(array_map(fn($v) => (float)($v['sold_fee'] ?? 0), $mv));
    $nagareFeeTotal  = array_sum(array_map(fn($v) => (float)($v['nagare_fee'] ?? 0), $mv));
    $otherFeeTotal   = array_sum(array_map(fn($v) => (float)($v['other_fee'] ?? 0), $mv));

    $commissionTotal = $grossSales * $commissionRate / 100;
    $totalReceived = $grossSales + $taxTotal + $recycleTotal;
    $totalVehicleDed = $listingFeeTotal + $soldFeeTotal + $nagareFeeTotal + $otherFeeTotal;
    $totalDed = $totalVehicleDed + $commissionTotal;
    $netPayout = $totalReceived - $totalDed;

    return compact('mv','count','grossSales','taxTotal','recycleTotal','listingFeeTotal','soldFeeTotal','nagareFeeTotal','otherFeeTotal','commissionTotal','commissionRate','totalReceived','totalVehicleDed','totalDed','netPayout');
}

// ─── ACTIVE TAB & STATS ───────────────────────────────────────────────────────
$tab      = $_GET['tab'] ?? 'members';
$tabs     = ['members'=>['icon'=>'👥','label'=>'Members'],'vehicles'=>['icon'=>'🚗','label'=>'Vehicles'],'statements'=>['icon'=>'📄','label'=>'Statements']];
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
      <div style="display:flex;align-items:center;gap:4px"><span style="color:var(--muted);font-size:11px">Commission</span><input class="inp mono" style="width:60px" type="number" step="0.1" name="commissionRate" value="<?= (float)($auction['commission_rate'] ?? 3.00) ?>"><span style="color:var(--muted);font-size:11px">%</span></div>
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
        <span class="chip-loc">📅 <?= h($a['date']) ?></span>
        <?php
        $daysLeft = (int)((strtotime($a['expires_at']) - time()) / 86400);
        $badgeClass = $daysLeft <= 0 ? 'expired' : ($daysLeft <= 3 ? 'expiring' : 'active');
        $badgeText = $daysLeft <= 0 ? 'Expired' : ($daysLeft . 'd left');
        ?>
        <span class="expiry-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
      </a>
    <?php endforeach; ?>
    <button class="auction-add" onclick="document.getElementById('addAuctionForm').style.display=document.getElementById('addAuctionForm').style.display==='none'?'flex':'none'">+ New Auction</button>
  </div>
  <?php if ($auction): ?>
  <div class="auction-meta">
    <b><?= h($auction['name']) ?></b> · <?= h($auction['date']) ?> · Commission: <?= (float)($auction['commission_rate'] ?? 3) ?>% · Expires: <?= h($auction['expires_at'] ?? 'N/A') ?>
  </div>
  <?php endif; ?>
</div>

<!-- ─── ADD AUCTION FORM (hidden by default) ─────────────────────── -->
<div id="addAuctionForm" style="display:none;padding:16px 28px;background:var(--bg2);border-bottom:1px solid var(--border)">
  <?= postForm('add_auction', 'members', $tok) ?>
    <div class="add-row ar-auction" style="margin-bottom:0">
      <div><label class="lbl">Auction Name *</label><input class="inp" name="name" placeholder="e.g. Tokyo Bay Auto Auction" required></div>
      <div><label class="lbl">Auction Date *</label><input class="inp" type="date" name="date" required></div>
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
    $s         = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_rate'] ?? 3.00));
    $editing   = isset($_GET['edit_member']) && (int)$_GET['edit_member'] === (int)$m['id'];
  ?>
  <?php if ($editing): ?>
  <div class="card card-pad mi">
    <div class="av"><?= mb_strtoupper(mb_substr($m['name'], 0, 1)) ?></div>
    <div style="flex:1">
      <?= postForm('update_member', 'members', $tok) ?>
        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <div class="add-row ar-members" style="margin-bottom:0">
          <div><label class="lbl">Full Name *</label><input class="inp" name="name" value="<?= h($m['name']) ?>" required></div>
          <div><label class="lbl">Phone</label><input class="inp" name="phone" value="<?= h($m['phone']) ?>"></div>
          <div><label class="lbl">Email</label><input class="inp" type="email" name="email" value="<?= h($m['email']) ?>"></div>
          <div style="display:flex;align-items:flex-end;gap:6px">
            <button class="btn btn-gold btn-sm" type="submit">Save</button>
            <a class="btn btn-dark btn-sm" href="?tab=members">Cancel</a>
          </div>
        </div>
      </form>
    </div>
  </div>
  <?php else: ?>
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
    <div style="display:flex;gap:6px;align-items:center">
      <a class="btn btn-dark btn-sm" href="?tab=members&edit_member=<?= (int)$m['id'] ?>">Edit</a>
      <?= postForm('remove_member', 'members', $tok) ?>
        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
        <button class="btn btn-ghost btn-sm" type="submit" onclick="return confirm('Remove <?= h($m['name']) ?> and all their vehicles?')">Remove</button>
      </form>
    </div>
  </div>
  <?php endif; ?>
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
        <input class="inp" id="memberSearch" name="memberSearch" placeholder="Type to search member…" autocomplete="off" required onfocus="showMemberResults()" oninput="filterMembers()">
        <input type="hidden" id="memberId" name="memberId" required>
        <div id="memberDropdown" class="member-dropdown" style="display:none"></div>
      </div>
      <div><label class="lbl">Make *</label><input class="inp" name="make" placeholder="Toyota" required></div>
      <div><label class="lbl">Model</label><input class="inp" name="model" placeholder="Prius"></div>
      <div><label class="lbl">Lot #</label><input class="inp" name="lot" placeholder="A-001"></div>
      <div><label class="lbl">Sold Price (¥)</label><input class="inp mono" type="number" name="soldPrice" placeholder="850000" min="0" style="-moz-appearance:textfield" oninput="this.value=this.value.replace(/[^0-9]/g,'')"></div>
      <div><label class="lbl">Recycle Fee (¥)</label><input class="inp mono" type="number" name="recycleFee" placeholder="15000" min="0" style="-moz-appearance:textfield"></div>
      <div><label class="lbl">Listing Fee (¥)</label><input class="inp mono" type="number" name="listingFee" placeholder="3000" min="0" style="-moz-appearance:textfield"></div>
      <div><label class="lbl">Sold Fee (¥)</label><input class="inp mono" type="number" name="soldFee" placeholder="25500" min="0" style="-moz-appearance:textfield"></div>
      <div><label class="lbl">Nagare Fee (¥)</label><input class="inp mono" type="number" name="nagareFee" placeholder="8000" min="0" style="-moz-appearance:textfield"></div>
      <div><label class="lbl">Other Fee (¥)</label><input class="inp mono" type="number" name="otherFee" placeholder="0" min="0" style="-moz-appearance:textfield"></div>
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
    <thead><tr><th>Lot #</th><th>Member</th><th>Vehicle</th><th class="r">Sold Price</th><th class="r">Tax 10%</th><th class="r">Recycle</th><th class="r">Listing</th><th class="r">Sold Fee</th><th class="r">Nagare</th><th class="r">Other</th><th class="r">Total</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php if (empty($vehicles)): ?>
      <tr><td colspan="14" style="padding:48px;text-align:center;color:var(--muted)">No vehicles yet for this auction.</td></tr>
    <?php else: ?>
      <?php foreach ($vehicles as $v):
        $owner = array_values(array_filter($members, fn($m) => (int)$m['id'] === (int)$v['member_id']))[0] ?? null;
        $editingV = isset($_GET['edit_vehicle']) && (int)$_GET['edit_vehicle'] === (int)$v['id'];
      ?>
      <?php if ($editingV): ?>
      <tr>
        <td colspan="9" style="padding:16px;background:var(--infield)">
          <?= postForm('update_vehicle', 'vehicles', $tok) ?>
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <div class="add-row ar-vehicles" style="margin-bottom:0">
              <div>
                <label class="lbl">Member *</label>
                <input class="inp edit-member-search" data-vid="<?= (int)$v['id'] ?>" placeholder="Type to search member…" autocomplete="off" value="<?= h($owner['name'] ?? '') ?>" oninput="filterEditMembers(this)">
                <input type="hidden" name="memberId" value="<?= (int)$v['member_id'] ?>">
                <div class="member-dropdown edit-member-dropdown" style="display:none"></div>
              </div>
              <div><label class="lbl">Make *</label><input class="inp" name="make" value="<?= h($v['make']) ?>" required></div>
              <div><label class="lbl">Model</label><input class="inp" name="model" value="<?= h($v['model']) ?>"></div>
              <div><label class="lbl">Lot #</label><input class="inp" name="lot" value="<?= h($v['lot']) ?>"></div>
              <div><label class="lbl">Sold Price (¥)</label><input class="inp mono" type="number" name="soldPrice" value="<?= (float)$v['sold_price'] ?>" min="0"></div>
              <div><label class="lbl">Recycle Fee (¥)</label><input class="inp mono" type="number" name="recycleFee" value="<?= (float)($v['recycle_fee'] ?? 0) ?>" min="0"></div>
              <div><label class="lbl">Listing Fee (¥)</label><input class="inp mono" type="number" name="listingFee" value="<?= (float)($v['listing_fee'] ?? 0) ?>" min="0"></div>
              <div><label class="lbl">Sold Fee (¥)</label><input class="inp mono" type="number" name="soldFee" value="<?= (float)($v['sold_fee'] ?? 0) ?>" min="0"></div>
              <div><label class="lbl">Nagare Fee (¥)</label><input class="inp mono" type="number" name="nagareFee" value="<?= (float)($v['nagare_fee'] ?? 0) ?>" min="0"></div>
              <div><label class="lbl">Other Fee (¥)</label><input class="inp mono" type="number" name="otherFee" value="<?= (float)($v['other_fee'] ?? 0) ?>" min="0"></div>
              <div style="display:flex;align-items:flex-end;gap:8px">
                <label style="display:flex;align-items:center;gap:5px;color:var(--muted);font-size:12px;cursor:pointer">
                  <input type="checkbox" name="sold" <?= $v['sold'] ? 'checked' : '' ?> style="accent-color:var(--gold)"> Sold
                </label>
                <button class="btn btn-gold btn-sm" type="submit">Save</button>
                <a class="btn btn-dark btn-sm" href="?tab=vehicles">Cancel</a>
              </div>
            </div>
          </form>
        </td>
      </tr>
      <?php else: ?>
      <tr>
        <td><span class="lot"><?= h($v['lot'] ?: '—') ?></span></td>
        <td><?= h($owner['name'] ?? '?') ?></td>
        <td style="color:var(--text2)"><?= h($v['make'] . ' ' . $v['model']) ?></td>
        <td style="text-align:right;font-family:var(--mono);color:<?= $v['sold'] ? 'var(--green)' : 'var(--muted)' ?>">
          <?= $v['sold'] ? fmt((float)$v['sold_price']) : '—' ?>
        </td>
        <td style="text-align:right;font-family:var(--mono);color:var(--text2);font-size:12px">
          <?= $v['sold'] ? fmt(round((float)$v['sold_price'] * 0.10)) : '—' ?>
        </td>
        <td style="text-align:right;font-family:var(--mono);color:var(--text2);font-size:12px">
          <?= $v['sold'] && (float)($v['recycle_fee'] ?? 0) > 0 ? fmt((float)$v['recycle_fee']) : '—' ?>
        </td>
        <td style="text-align:right;font-family:var(--mono);color:var(--red);font-size:12px">
          <?= $v['sold'] && (float)($v['listing_fee'] ?? 0) > 0 ? '−' . fmt((float)$v['listing_fee']) : '—' ?>
        </td>
        <td style="text-align:right;font-family:var(--mono);color:var(--red);font-size:12px">
          <?= $v['sold'] && (float)($v['sold_fee'] ?? 0) > 0 ? '−' . fmt((float)$v['sold_fee']) : '—' ?>
        </td>
        <td style="text-align:right;font-family:var(--mono);color:var(--red);font-size:12px">
          <?= $v['sold'] && (float)($v['nagare_fee'] ?? 0) > 0 ? '−' . fmt((float)$v['nagare_fee']) : '—' ?>
        </td>
        <td style="text-align:right;font-family:var(--mono);color:var(--red);font-size:12px">
          <?= $v['sold'] && (float)($v['other_fee'] ?? 0) > 0 ? '−' . fmt((float)$v['other_fee']) : '—' ?>
        </td>
        <td style="text-align:right;font-family:var(--mono);color:<?= $v['sold'] ? 'var(--gold)' : 'var(--muted)' ?>;font-weight:700">
          <?php if ($v['sold']): $vTotal = (float)$v['sold_price'] + round((float)$v['sold_price'] * 0.10) + (float)($v['recycle_fee'] ?? 0) - (float)($v['listing_fee'] ?? 0) - (float)($v['sold_fee'] ?? 0) - (float)($v['nagare_fee'] ?? 0) - (float)($v['other_fee'] ?? 0); ?>
          <?= fmt($vTotal) ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td>
          <?= postForm('toggle_sold', 'vehicles', $tok) ?>
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <button class="sb <?= $v['sold'] ? 'sy' : 'sn' ?>" type="submit"><?= $v['sold'] ? '✓ SOLD' : '✗ UNSOLD' ?></button>
          </form>
        </td>
        <td>
          <div style="display:flex;gap:4px;align-items:center">
            <a class="btn btn-dark btn-sm" href="?tab=vehicles&edit_vehicle=<?= (int)$v['id'] ?>" style="font-size:11px;padding:4px 10px">Edit</a>
            <?= postForm('remove_vehicle', 'vehicles', $tok) ?>
              <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
              <button class="btn-icon" type="submit" onclick="return confirm('Remove this vehicle?')">×</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
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
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_rate'] ?? 3.00));
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
        <?php foreach ($s['mv'] as $v): $vTax = round((float)$v['sold_price'] * 0.10); $vRecycle = (float)($v['recycle_fee'] ?? 0); ?>
        <div class="vr">
          <span class="vr-car"><span class="vr-lot"><?= h($v['lot'] ?: '—') ?></span><?= h($v['make'] . ' ' . $v['model']) ?></span>
          <span class="vr-p"><?= fmt((float)$v['sold_price']) ?></span>
        </div>
        <?php if ($vTax > 0 || $vRecycle > 0): ?>
        <div style="padding:2px 0 6px 16px;font-size:11px;color:var(--muted);display:flex;justify-content:space-between">
          <span>+ Tax 10%: <?= fmt($vTax) ?><?php if ($vRecycle > 0): ?> + Recycle: <?= fmt($vRecycle) ?><?php endif; ?></span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <div class="sg"><span class="sg-l">Gross Sales</span><span class="sg-n"><?= fmt($s['grossSales']) ?></span></div>
        <?php if ($s['taxTotal'] > 0): ?>
        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px"><span style="color:var(--text2)">+ Consumption Tax 10%</span><span style="font-family:var(--mono);color:var(--green)"><?= fmt($s['taxTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['recycleTotal'] > 0): ?>
        <div style="display:flex;justify-content:space-between;padding:4px 0;font-size:13px"><span style="color:var(--text2)">+ Recycle Fees</span><span style="font-family:var(--mono);color:var(--green)"><?= fmt($s['recycleTotal']) ?></span></div>
        <?php endif; ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-top:2px solid var(--border);margin-top:6px;font-weight:700"><span style="color:var(--gold)">Total Received</span><span style="font-family:var(--mono);color:var(--gold);font-size:15px"><?= fmt($s['totalReceived']) ?></span></div>
      </div>
        <div class="ssl">Vehicle Fee Deductions</div>
        <?php if ($s['listingFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Listing Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['listingFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['soldFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Sold Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['soldFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['nagareFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Nagare Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['nagareFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['otherFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Other Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['otherFeeTotal']) ?></span></div>
    <?php endif; ?>
        <?php if ($s['commissionTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Commission <?= $s['commissionRate'] ?>%</span><span class="dr-a">−<?= fmt($s['commissionTotal']) ?></span></div>
        <?php endif; ?>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>
</div>

<script>
const membersData = <?= json_encode(array_map(fn($m) => ['id'=>(int)$m['id'], 'name'=>$m['name'], 'phone'=>$m['phone']], $members)) ?>;

function filterMembers() {
  const q = document.getElementById('memberSearch').value.toLowerCase();
  const dd = document.getElementById('memberDropdown');
  const hidden = document.getElementById('memberId');
  hidden.value = '';
  if (!q) { dd.style.display = 'none'; return; }
  const filtered = membersData.filter(m => m.name.toLowerCase().includes(q) || m.phone.includes(q));
  if (!filtered.length) { dd.style.display = 'none'; return; }
  dd.innerHTML = filtered.map(m => `<div class="member-dropdown-item" data-id="${m.id}" onclick="selectMember(${m.id},'${m.name.replace(/'/g,"\\'")}')">${m.name}<span class="mdi-phone">${m.phone}</span></div>`).join('');
  dd.style.display = 'block';
}

function selectMember(id, name) {
  document.getElementById('memberSearch').value = name;
  document.getElementById('memberId').value = id;
  document.getElementById('memberDropdown').style.display = 'none';
}

function showMemberResults() {
  const q = document.getElementById('memberSearch').value;
  if (q) filterMembers();
}

function filterEditMembers(el) {
  const q = el.value.toLowerCase();
  const dd = el.nextElementSibling.nextElementSibling;
  const hidden = el.nextElementSibling;
  hidden.value = '';
  if (!q) { dd.style.display = 'none'; return; }
  const filtered = membersData.filter(m => m.name.toLowerCase().includes(q) || m.phone.includes(q));
  if (!filtered.length) { dd.style.display = 'none'; return; }
  dd.innerHTML = filtered.map(m => `<div class="member-dropdown-item" data-id="${m.id}" onclick="selectEditMember(this,${m.id},'${m.name.replace(/'/g,"\\'")}')">${m.name}<span class="mdi-phone">${m.phone}</span></div>`).join('');
  dd.style.display = 'block';
}

function selectEditMember(el, id, name) {
  const row = el.closest('td');
  row.querySelector('.edit-member-search').value = name;
  row.querySelector('input[name=memberId]').value = id;
  el.closest('.member-dropdown').style.display = 'none';
}

document.addEventListener('click', function(e) {
  document.querySelectorAll('.member-dropdown').forEach(dd => {
    if (!dd.contains(e.target) && e.target.id !== 'memberSearch' && !e.target.classList.contains('edit-member-search')) dd.style.display = 'none';
  });
});

</script>
</body>
</html>
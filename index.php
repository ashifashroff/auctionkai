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
    return "<form method='POST' action='index.php' style='display:contents'>"
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
        $stmt = $db->prepare("UPDATE auction SET name=?, date=?, commission_fee=? WHERE id=? AND user_id=?");
        $stmt->execute([trim($_POST['name']), trim($_POST['date']), (float)($_POST['commissionFee'] ?? 3.00), $activeAuctionId, $userId]);
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
<link rel="stylesheet" href="css/style.css">
<?php include 'css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<!-- ─── TOP BAR ─────────────────────────────────────── -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-6 sticky top-0 z-50 animate-slide-down">
  <div class="shrink-0">
    <div class="text-ak-gold font-bold text-lg tracking-tight">⚡ AuctionKai <span class="text-[10px] bg-ak-border text-ak-muted px-2 py-0.5 rounded ml-1 font-mono">MySQL</span></div>
    <div class="text-ak-muted text-[11px]">Settlement Management System</div>
  </div>
  <?php if ($auction): ?>
  <div class="flex items-center gap-2 flex-1 justify-center">
    <?= postForm('save_auction', $tab, $tok) ?>
      <div class="flex items-center gap-2 flex-wrap">
        <input class="inp w-56" name="name" value="<?= h($auction['name']) ?>" placeholder="Auction name">
        <input class="inp w-36" type="date" name="date" value="<?= h($auction['date']) ?>">
        <div class="flex items-center gap-1"><span class="text-ak-muted text-[11px]">Commission</span><input class="inp font-mono w-16" type="number" step="1" name="commissionFee" value="<?= (float)($auction['commission_fee'] ?? 3300) ?>"><span class="text-ak-muted text-[11px]">¥/member</span></div>
        <button class="btn btn-dark btn-sm" type="submit">Save</button>
      </div>
    </form>
  </div>
  <?php endif; ?>
  <div class="flex items-center gap-3 shrink-0 ml-auto">
    <a href="profile.php" class="flex items-center gap-2 no-underline hover:opacity-80 transition-opacity">
      <div class="w-8 h-8 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-sm"><?= mb_strtoupper(mb_substr($userName, 0, 1)) ?></div>
      <div><div class="text-ak-text text-sm font-semibold"><?= h($userName) ?></div><div class="text-ak-muted text-[10px] capitalize"><?= h($userRole) ?></div></div>
    </a>
    <a href="logout.php" class="text-ak-muted text-xs hover:text-ak-red transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">Logout</a>
  </div>
</div>

<!-- ─── AUCTION SELECTOR ────────────────────────────── -->
<div class="bg-ak-bg border-b border-ak-border px-7 py-3">
  <div class="flex gap-2 flex-wrap items-center">
    <?php foreach ($allAuctions as $a): ?>
      <a class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all duration-200 <?= (int)$a['id'] === $activeAuctionId ? 'bg-ak-gold text-ak-bg animate-pulse-gold' : 'bg-ak-card text-ak-text2 hover:bg-ak-border' ?>" href="?auction_id=<?= (int)$a['id'] ?>&tab=<?= h($tab) ?>">
        <?= h($a['name']) ?>
        <span class="text-[10px] opacity-70">📅 <?= h($a['date']) ?></span>
        <?php
        $daysLeft = (int)((strtotime($a['expires_at']) - time()) / 86400);
        $badgeClass = $daysLeft <= 0 ? 'bg-ak-red/20 text-ak-red' : ($daysLeft <= 3 ? 'bg-yellow-500/20 text-yellow-400' : 'bg-ak-green/20 text-ak-green');
        $badgeText = $daysLeft <= 0 ? 'Expired' : ($daysLeft . 'd left');
        ?>
        <span class="text-[10px] px-1.5 py-0.5 rounded <?= $badgeClass ?>"><?= $badgeText ?></span>
      </a>
    <?php endforeach; ?>
    <button class="px-3 py-2 rounded-lg border border-dashed border-ak-border text-ak-muted text-xs hover:border-ak-gold hover:text-ak-gold transition-all duration-200" onclick="document.getElementById('addAuctionForm').style.display=document.getElementById('addAuctionForm').style.display==='none'?'flex':'none'">+ New Auction</button>
  </div>
  <?php if ($auction): ?>
  <div class="text-ak-muted text-xs mt-2">
    <b class="text-ak-text"><?= h($auction['name']) ?></b> · <?= h($auction['date']) ?> · Commission: ¥<?= number_format((float)($auction['commission_fee'] ?? 3300)) ?>/member · Expires: <?= h($auction['expires_at'] ?? 'N/A') ?>
  </div>
  <?php endif; ?>
</div>

<!-- ─── ADD AUCTION FORM ─────────────────────────────── -->
<div id="addAuctionForm" class="hidden bg-ak-bg2 border-b border-ak-border px-7 py-4 animate-slide-down">
  <?= postForm('add_auction', 'members', $tok) ?>
    <div class="add-row ar-auction mb-0">
      <div><label class="lbl">Auction Name *</label><input class="inp" name="name" placeholder="e.g. Tokyo Bay Auto Auction" required></div>
      <div><label class="lbl">Auction Date *</label><input class="inp" type="date" name="date" required></div>
      <div class="flex items-end pt-[22px]"><button class="btn btn-gold" type="submit">+ Create</button></div>
    </div>
  </form>
</div>

<!-- ─── TABS ────────────────────────────────────────── -->
<div class="bg-ak-bg border-b border-ak-border px-7 flex items-center gap-1">
  <?php foreach ($tabs as $key => $t): ?>
    <a class="px-5 py-3 text-sm font-semibold transition-all duration-200 border-b-2 <?= $tab === $key ? 'text-ak-gold border-ak-gold' : 'text-ak-muted border-transparent hover:text-ak-text2' ?>" href="?tab=<?= $key ?><?= $activeAuctionId ? '&auction_id='.$activeAuctionId : '' ?>"><?= $t['icon'] ?> <?= $t['label'] ?></a>
  <?php endforeach; ?>
  <div class="ml-auto text-xs text-ak-muted flex gap-4">
    <span><b class="text-ak-text"><?= count($members) ?></b> members</span>
    <span><b class="text-ak-green"><?= $totalSold ?></b> sold / <b class="text-ak-text"><?= count($vehicles) ?></b> total</span>
  </div>
</div>

<!-- ─── CONTENT ─────────────────────────────────────── -->
<div class="p-7 max-w-[1400px] mx-auto animate-fade-in">

<?php if (!$auction): ?>
  <div class="text-center py-24">
    <h2 class="text-2xl font-bold text-ak-muted mb-3">No Auctions Yet</h2>
    <p class="text-ak-muted2">Click <strong class="text-ak-gold">"+ New Auction"</strong> above to create your first auction.</p>
  </div>

<?php elseif ($tab === 'members'): ?>
<h2 class="text-lg font-bold mb-5">Members / Sellers — <?= h($auction['name']) ?></h2>
<div class="bg-ak-card rounded-xl p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Add New Member</div>
  <?= postForm('add_member', 'members', $tok) ?>
    <div class="grid grid-cols-4 gap-3">
      <div><label class="lbl">Full Name *</label><input class="inp" name="name" placeholder="e.g. Ahmad Hassan" required></div>
      <div><label class="lbl">Phone</label><input class="inp" name="phone" placeholder="090-xxxx-xxxx"></div>
      <div><label class="lbl">Email</label><input class="inp" type="email" name="email" placeholder="email@example.com"></div>
      <div class="flex items-end pt-[22px]"><button class="btn btn-gold" type="submit">+ Add</button></div>
    </div>
  </form>
</div>
<div class="flex flex-col gap-2.5">
<?php if (empty($members)): ?>
  <div class="bg-ak-card rounded-xl p-12 text-center text-ak-muted border border-ak-border">No members yet for this auction.</div>
<?php else: ?>
  <?php foreach ($members as $m):
    $mv        = array_filter($vehicles, fn($v) => (int)$v['member_id'] === (int)$m['id']);
    $soldCount = count(array_filter($mv, fn($v) => $v['sold']));
    $s         = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300));
    $editing   = isset($_GET['edit_member']) && (int)$_GET['edit_member'] === (int)$m['id'];
  ?>
  <?php if ($editing): ?>
  <div class="bg-ak-card rounded-xl p-4 border border-ak-gold/30 flex items-center gap-4 animate-fade-in">
    <div class="w-10 h-10 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-lg shrink-0"><?= mb_strtoupper(mb_substr($m['name'], 0, 1)) ?></div>
    <div class="flex-1 min-w-0">
      <div class="text-ak-text font-semibold"><?= h($m['name']) ?></div>
      <div class="text-ak-muted text-xs"><?= h($m['phone']) ?> · <?= h($m['email']) ?></div>
    </div>
  </div>
  <?php else: ?>
  <div class="bg-ak-card rounded-xl p-4 border border-ak-border flex items-center gap-4 hover:border-ak-border/80 transition-all duration-200 animate-fade-in-up">
    <div class="w-10 h-10 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-lg shrink-0"><?= mb_strtoupper(mb_substr($m['name'], 0, 1)) ?></div>
    <div class="flex-1 min-w-0">
      <div class="text-ak-text font-semibold cursor-pointer hover:text-ak-gold transition-colors" onclick="openMemberDetail(<?= (int)$m['id'] ?>)"><?= h($m['name']) ?></div>
      <div class="text-ak-muted text-xs"><?= h($m['phone']) ?> · <?= h($m['email']) ?></div>
    </div>
    <div class="text-center px-3">
      <div class="text-ak-text font-bold text-lg"><?= count($mv) ?></div>
      <div class="text-ak-muted text-[10px]"><?= $soldCount ?> sold</div>
    </div>
    <div class="text-right px-3">
      <div class="text-ak-gold font-mono font-bold"><?= fmt($s['netPayout']) ?></div>
      <div class="text-ak-muted text-[10px]">net payout</div>
    </div>
    <div class="flex gap-1.5 items-center">
      <button class="btn btn-dark btn-sm" onclick="openEditMemberModal(<?= (int)$m['id'] ?>)">Edit</button>
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
<h2 class="text-lg font-bold mb-5">Vehicle Listings — <?= h($auction['name']) ?></h2>
<div class="bg-ak-card rounded-xl p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Add Vehicle</div>
  <form id="addVehicleForm" onsubmit="return submitAddVehicle(event)">
    <div class="grid grid-cols-6 gap-2" id="addVehicleFields">
      <div class="col-span-2 relative">
        <label class="lbl">Member *</label>
        <input class="inp" id="memberSearch" name="memberSearch" placeholder="Type to search member…" autocomplete="off" required onfocus="showMemberResults()" oninput="filterMembers()">
        <input type="hidden" id="memberId" name="memberId" required>
        <div id="memberDropdown" class="member-dropdown" style="display:none"></div>
      </div>
      <div><label class="lbl">Make *</label><input class="inp" id="add_make" name="make" placeholder="Toyota" required></div>
      <div><label class="lbl">Model</label><input class="inp" id="add_model" name="model" placeholder="Prius"></div>
      <div><label class="lbl">Lot #</label><input class="inp" id="add_lot" name="lot" placeholder="A-001"></div>
      <div><label class="lbl">Sold Price (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_soldPrice" name="soldPrice" placeholder="850000" min="0"></div>
      <div><label class="lbl">Recycle Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_recycleFee" name="recycleFee" placeholder="15000" min="0"></div>
      <div><label class="lbl">Listing Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_listingFee" name="listingFee" placeholder="3000" min="0"></div>
      <div><label class="lbl">Sold Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_soldFee" name="soldFee" placeholder="25500" min="0"></div>
      <div class="nagare-field"><label class="lbl">Nagare Fee (¥)</label><input class="inp font-mono" type="number" id="add_nagareFee" name="nagareFee" placeholder="8000" min="0" disabled></div>
      <div><label class="lbl">Other Fee (¥)</label><input class="inp font-mono" type="number" id="add_otherFee" name="otherFee" placeholder="0" min="0"></div>
      <div class="flex items-end pt-[22px] gap-2">
        <label class="flex items-center gap-1.5 text-ak-muted text-xs cursor-pointer">
          <input type="checkbox" id="add_sold" name="sold" checked class="accent-ak-gold" onchange="toggleSoldFields(this.checked)"> Sold
        </label>
        <button class="btn btn-gold" type="submit" id="addVehicleBtn">Add</button>
      </div>
    </div>
    <div id="addVehicleMsg" class="hidden mt-2.5 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
  </form>
</div>
<div class="bg-ak-card rounded-xl border border-ak-border overflow-x-auto">
  <table class="vt">
    <thead><tr><th>Lot #</th><th>Member</th><th>Vehicle</th><th class="r">Sold Price</th><th class="r">Tax 10%</th><th class="r">Recycle</th><th class="r">Listing</th><th class="r">Sold Fee</th><th class="r">Nagare</th><th class="r">Other</th><th class="r">Total</th><th>Status</th><th class="w-[90px]">Actions</th></tr></thead>
    <tbody>
    <?php if (empty($vehicles)): ?>
      <tr><td colspan="14" class="text-center text-ak-muted py-12">No vehicles yet for this auction.</td></tr>
    <?php else: ?>
      <?php foreach ($vehicles as $v):
        $owner = array_values(array_filter($members, fn($m) => (int)$m['id'] === (int)$v['member_id']))[0] ?? null;
      ?>
      <tr data-vid="<?= (int)$v['id'] ?>" class="animate-fade-in">
        <td><span class="lot"><?= h($v['lot'] ?: '—') ?></span></td>
        <td data-field="member"><?= h($owner['name'] ?? '?') ?></td>
        <td class="text-ak-text2" data-field="vehicle"><?= h($v['make'] . ' ' . $v['model']) ?></td>
        <td class="text-right font-mono <?= $v['sold'] ? 'text-ak-green' : 'text-ak-muted' ?>" data-field="sold_price">
          <?= $v['sold'] ? fmt((float)$v['sold_price']) : '—' ?>
        </td>
        <td class="text-right font-mono text-ak-text2 text-xs" data-field="tax">
          <?= $v['sold'] ? fmt(round((float)$v['sold_price'] * 0.10)) : '—' ?>
        </td>
        <td class="text-right font-mono text-ak-text2 text-xs" data-field="recycle">
          <?= $v['sold'] && (float)($v['recycle_fee'] ?? 0) > 0 ? fmt((float)$v['recycle_fee']) : '—' ?>
        </td>
        <td class="text-right font-mono text-ak-red text-xs" data-field="listing">
          <?= $v['sold'] && (float)($v['listing_fee'] ?? 0) > 0 ? '−' . fmt((float)$v['listing_fee']) : '—' ?>
        </td>
        <td class="text-right font-mono text-ak-red text-xs" data-field="sold_fee">
          <?= $v['sold'] && (float)($v['sold_fee'] ?? 0) > 0 ? '−' . fmt((float)$v['sold_fee']) : '—' ?>
        </td>
        <td class="text-right font-mono text-ak-red text-xs" data-field="nagare">
          <?= !$v['sold'] && (float)($v['nagare_fee'] ?? 0) > 0 ? '−' . fmt((float)$v['nagare_fee']) : '—' ?>
        </td>
        <td class="text-right font-mono text-ak-red text-xs" data-field="other">
          <?= (float)($v['other_fee'] ?? 0) > 0 ? '−' . fmt((float)$v['other_fee']) : '—' ?>
        </td>
        <td class="text-right font-mono font-bold <?= $v['sold'] ? 'text-ak-gold' : 'text-ak-muted' ?>" data-field="total">
          <?php if ($v['sold']): $vTotal = (float)$v['sold_price'] + round((float)$v['sold_price'] * 0.10) + (float)($v['recycle_fee'] ?? 0) - (float)($v['listing_fee'] ?? 0) - (float)($v['sold_fee'] ?? 0) - (float)($v['nagare_fee'] ?? 0) - (float)($v['other_fee'] ?? 0); ?>
          <?= fmt($vTotal) ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td data-field="status">
          <?= postForm('toggle_sold', 'vehicles', $tok) ?>
            <input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
            <button class="sb <?= $v['sold'] ? 'sy' : 'sn' ?>" type="submit"><?= $v['sold'] ? '✓ SOLD' : '✗ UNSOLD' ?></button>
          </form>
        </td>
        <td class="whitespace-nowrap">
          <div class="flex gap-1 items-center">
            <button class="btn btn-dark btn-sm" onclick="openEditModal(<?= (int)$v['id'] ?>)">Edit</button>
            <button class="btn-icon" onclick="deleteVehicle(<?= (int)$v['id'] ?>, this)">×</button>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php elseif ($tab === 'statements'): ?>
<div class="flex justify-between items-center mb-6">
  <h2 class="text-lg font-bold">Settlement Statements — <?= h($auction['name']) ?></h2>
  <a class="btn btn-dark" href="pdf.php?all=1&auction_id=<?= $activeAuctionId ?>" target="_blank">↓ Print All PDFs</a>
</div>
<?php if (empty($members)): ?>
  <div class="bg-ak-card rounded-xl p-8 text-center text-ak-muted border border-ak-border">No sales history available for this auction.</div>
<?php else: ?>
  <?php $hasSales = false; ?>
  <?php foreach ($members as $m):
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300));
    if ($s['count'] === 0) continue;
    $hasSales = true;
    $emailSubject = urlencode("Settlement Statement – {$auction['name']} {$auction['date']}");
    $emailBody    = urlencode("Dear {$m['name']},\n\nPlease find your settlement for {$auction['name']} on {$auction['date']}.\n\nVehicles Sold: {$s['count']}\nGross Sales: " . fmt($s['grossSales']) . "\nTotal Deductions: " . fmt($s['totalDed']) . "\n\nNET PAYOUT: " . fmt($s['netPayout']) . "\n\nThank you.");
  ?>
  <div class="bg-ak-card rounded-xl border border-ak-border mb-5 overflow-hidden animate-fade-in-up">
    <div class="sh">
      <div><div class="sn2"><?= h($m['name']) ?></div><div class="sm"><?= h($m['email']) ?> · <?= h($m['phone']) ?></div></div>
      <div class="sa">
        <a class="btn-email" href="mailto:<?= h($m['email']) ?>?subject=<?= $emailSubject ?>&body=<?= $emailBody ?>">✉ Send Email</a>
        <a class="btn btn-gold btn-sm" href="pdf.php?member=<?= (int)$m['id'] ?>&auction_id=<?= $activeAuctionId ?>" target="_blank">↓ PDF</a>
      </div>
    </div>
    <div class="sb2">
      <div class="sl">
        <div class="ssl">Sold Vehicles (<?= $s['count'] ?>)</div>
        <?php foreach ($s['mv'] as $v): $vTax = round((float)$v['sold_price'] * 0.10); $vRecycle = (float)($v['recycle_fee'] ?? 0); ?>
        <div class="vr">
          <span class="vr-car"><span class="vr-lot"><?= h($v['lot'] ?: '—') ?></span><?= h($v['make'] . ' ' . $v['model']) ?></span>
          <span class="vr-p"><?= fmt((float)$v['sold_price']) ?></span>
        </div>
        <?php if ($vTax > 0 || $vRecycle > 0): ?>
        <div class="pl-4 py-0.5 pb-1.5 text-[11px] text-ak-muted flex justify-between">
          <span>+ Tax 10%: <?= fmt($vTax) ?><?php if ($vRecycle > 0): ?> + Recycle: <?= fmt($vRecycle) ?><?php endif; ?></span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <div class="sg"><span class="sg-l">Gross Sales</span><span class="sg-n"><?= fmt($s['grossSales']) ?></span></div>
        <?php if ($s['taxTotal'] > 0): ?>
        <div class="flex justify-between py-1 text-[13px]"><span class="text-ak-text2">+ Consumption Tax 10%</span><span class="font-mono text-ak-green"><?= fmt($s['taxTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['recycleTotal'] > 0): ?>
        <div class="flex justify-between py-1 text-[13px]"><span class="text-ak-text2">+ Recycle Fees</span><span class="font-mono text-ak-green"><?= fmt($s['recycleTotal']) ?></span></div>
        <?php endif; ?>
        <div class="flex justify-between py-2 border-t-2 border-ak-border mt-1.5 font-bold"><span class="text-ak-gold">Total Received</span><span class="font-mono text-ak-gold text-[15px]"><?= fmt($s['totalReceived']) ?></span></div>
      </div>
      <div class="sr">
        <div class="ssl">Deductions</div>
        <?php if ($s['listingFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Listing Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['listingFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['soldFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Sold Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['soldFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['nagareFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Nagare Fee ×<?= $s['unsoldCount'] ?></span><span class="dr-a">−<?= fmt($s['nagareFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['otherFeeTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Other Fee ×<?= $s['count'] ?></span><span class="dr-a">−<?= fmt($s['otherFeeTotal']) ?></span></div>
        <?php endif; ?>
        <?php if ($s['commissionTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Commission ¥<?= number_format($s['commissionFee']) ?>/member</span><span class="dr-a">−<?= fmt($s['commissionTotal']) ?></span></div>
        <?php endif; ?>
        <div class="dt"><span class="dt-l">Total Deductions</span><span class="dt-n">−<?= fmt($s['totalDed']) ?></span></div>
        <div class="np"><span class="np-l">NET PAYOUT / お支払い額</span><span class="np-n"><?= fmt($s['netPayout']) ?></span></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>
<script>const membersData = <?= json_encode(array_map(fn($m) => ['id'=>(int)$m['id'], 'name'=>$m['name'], 'phone'=>$m['phone']], $members)) ?>;const activeAuctionId = <?= (int)$activeAuctionId ?>;</script>
<script src="js/app.js"></script>

<!-- Edit Vehicle Modal -->
<div id="editModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center hidden" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[720px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-ak-gold text-lg font-bold">Edit Vehicle</h3>
      <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeEditModal()">×</button>
    </div>
    <div id="modalMsg" class="hidden mb-3 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
    <form id="editForm" onsubmit="return submitEditForm(event)">
      <input type="hidden" id="edit_id" name="id">
      <div class="grid grid-cols-2 gap-3 max-[600px]:grid-cols-1">
        <div class="relative">
          <label class="lbl">Member *</label>
          <input class="inp" id="edit_memberSearch" placeholder="Type to search member…" autocomplete="off" oninput="filterModalMembers()" required>
          <input type="hidden" id="edit_memberId" name="memberId" required>
          <div id="edit_memberDropdown" class="member-dropdown" style="display:none"></div>
        </div>
        <div><label class="lbl">Make *</label><input class="inp" id="edit_make" name="make" required></div>
        <div><label class="lbl">Model</label><input class="inp" id="edit_model" name="model"></div>
        <div><label class="lbl">Lot #</label><input class="inp" id="edit_lot" name="lot"></div>
        <div><label class="lbl">Sold Price (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_soldPrice" name="soldPrice" min="0"></div>
        <div><label class="lbl">Recycle Fee (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_recycleFee" name="recycleFee" min="0"></div>
        <div><label class="lbl">Listing Fee (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_listingFee" name="listingFee" min="0"></div>
        <div><label class="lbl">Sold Fee (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_soldFee" name="soldFee" min="0"></div>
        <div class="modal-nagare-field"><label class="lbl">Nagare Fee (¥)</label><input class="inp font-mono" type="number" id="edit_nagareFee" name="nagareFee" min="0" disabled></div>
        <div><label class="lbl">Other Fee (¥)</label><input class="inp font-mono" type="number" id="edit_otherFee" name="otherFee" min="0"></div>
      </div>
      <div class="flex items-center gap-3 mt-4 pt-4 border-t border-ak-border">
        <label class="flex items-center gap-1.5 text-ak-muted text-xs cursor-pointer">
          <input type="checkbox" id="edit_sold" name="sold" class="accent-ak-gold" onchange="toggleModalSoldFields(this.checked)"> Sold
        </label>
        <div class="flex-1"></div>
        <button type="button" class="btn btn-dark btn-sm" onclick="closeEditModal()">Cancel</button>
        <button type="submit" class="btn btn-gold btn-sm" id="editSubmitBtn">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<!-- Member Detail Modal -->
<div id="memberDetailModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[800px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <div>
        <h3 class="text-ak-gold text-lg font-bold" id="mdName">Member</h3>
        <div class="text-ak-muted text-xs mt-1" id="mdContact"></div>
      </div>
      <div class="flex items-center gap-3">
        <a id="mdPdfLink" class="btn btn-gold btn-sm" href="#" target="_blank">↓ PDF</a>
        <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeMemberDetail()">×</button>
      </div>
    </div>
    <div id="mdContent"><div class="text-center text-ak-muted py-12">Loading…</div></div>
  </div>
</div>

<!-- Edit Member Modal -->
<div id="editMemberModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[500px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-ak-gold text-lg font-bold">Edit Member</h3>
      <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeEditMemberModal()">×</button>
    </div>
    <div id="editMemberMsg" class="hidden mb-3 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
    <form id="editMemberForm" onsubmit="return submitEditMember(event)">
      <input type="hidden" id="em_id" name="id">
      <div class="mb-4"><label class="lbl">Full Name *</label><input class="inp" id="em_name" name="name" required></div>
      <div class="mb-4"><label class="lbl">Phone</label><input class="inp" id="em_phone" name="phone" placeholder="090-xxxx-xxxx"></div>
      <div class="mb-5"><label class="lbl">Email</label><input class="inp" type="email" id="em_email" name="email" placeholder="email@example.com"></div>
      <div class="flex justify-end gap-2 pt-4 border-t border-ak-border">
        <button type="button" class="btn btn-dark btn-sm" onclick="closeEditMemberModal()">Cancel</button>
        <button type="submit" class="btn btn-gold btn-sm" id="emSubmitBtn">Save</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
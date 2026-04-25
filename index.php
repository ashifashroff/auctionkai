<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
session_start();

// ─── AUTH CHECK ────────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

if (empty($_SESSION['tok'])) $_SESSION['tok'] = bin2hex(random_bytes(16));
$tok = $_SESSION['tok'];
$userId = (int)$_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$userRole = $_SESSION['user_role'] ?? 'user';

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function postForm(string $action, string $tabTarget, string $tok): string {
    return "<form method='POST' action='index.php' style='display:contents' data-parsley-validate>"
         . "<input type='hidden' name='action' value='" . h($action) . "'>"
         . "<input type='hidden' name='tab'    value='" . h($tabTarget) . "'>"
         . "<input type='hidden' name='_tok'   value='" . h($tok) . "'>";
}

// ─── LOAD DB ─────────────────────────────────────────────────────────────────
$db = db();

// ─── ACTIVE AUCTION (selected via navbar or session) ─────────────────────────
$allAuctions_q = $db->prepare("SELECT * FROM auction WHERE user_id=? ORDER BY date DESC, id DESC");
$allAuctions_q->execute([$userId]);
$allAuctions = $allAuctions_q->fetchAll();

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
$expiredQ = $db->prepare("SELECT id FROM auction WHERE user_id=? AND expires_at < ?");
$expiredQ->execute([$userId, $today]);
$expiredAuctions = $expiredQ->fetchAll();
foreach ($expiredAuctions as $ea) {
    // Delete all vehicles for this auction (sold + unsold)
    $db->prepare("DELETE FROM vehicles WHERE auction_id=?")->execute([(int)$ea['id']]);

    // Delete the auction itself
    $db->prepare("DELETE FROM auction WHERE id=? AND user_id=?")->execute([(int)$ea['id'], $userId]);
}
// Refresh if current auction was deleted
if ($activeAuctionId) { $chkAuc = $db->prepare("SELECT id FROM auction WHERE id=? AND user_id=?"); $chkAuc->execute([$activeAuctionId, $userId]); if (!$chkAuc->fetch()) $activeAuctionId = 0; }
if (!$activeAuctionId) {
    $allAuctions_q = $db->prepare("SELECT * FROM auction WHERE user_id=? ORDER BY date DESC, id DESC");
    $allAuctions_q->execute([$userId]);
    $allAuctions = $allAuctions_q->fetchAll();
    $auction = !empty($allAuctions) ? $allAuctions[0] : null;
    $activeAuctionId = $auction ? (int)$auction['id'] : 0;
    $_SESSION['auction_id'] = $activeAuctionId;
} else {
    $allAuctions_q = $db->prepare("SELECT * FROM auction WHERE user_id=? ORDER BY date DESC, id DESC");
    $allAuctions_q->execute([$userId]);
    $allAuctions = $allAuctions_q->fetchAll();
    $auction = null;
    foreach ($allAuctions as $a) { if ((int)$a['id'] === $activeAuctionId) { $auction = $a; break; } }
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
            $dup = $db->prepare("SELECT id FROM members WHERE user_id=? AND name=?");
            $dup->execute([$userId, $name]);
            if (!$dup->fetch()) {
                $stmt = $db->prepare("INSERT INTO members (user_id, name, phone, email) VALUES (?,?,?,?)");
                $stmt->execute([$userId, $name, trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? '')]);
            }
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
            $stmt = $db->prepare("INSERT INTO vehicles (auction_id, member_id, make, model, lot, sold_price, recycle_fee, listing_fee, sold_fee, nagare_fee, sold) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                $activeAuctionId, $memberId, $make,
                trim($_POST['model']    ?? ''),
                trim($_POST['lot']      ?? ''),
                (float)($_POST['soldPrice'] ?? 0),
                (float)($_POST['recycleFee'] ?? 0),
                (float)($_POST['listingFee'] ?? 0),
                (float)($_POST['soldFee'] ?? 0),
                (float)($_POST['nagareFee'] ?? 0),
                isset($_POST['sold']) ? 1 : 0,
            ]);
        }
    }

    elseif ($action === 'update_vehicle') {
        $id   = (int)$_POST['id'];
        $make = trim($_POST['make'] ?? '');
        if ($make !== '') {
            $stmt = $db->prepare("UPDATE vehicles SET member_id=?, make=?, model=?, lot=?, sold_price=?, recycle_fee=?, listing_fee=?, sold_fee=?, nagare_fee=?, sold=? WHERE id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)");
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
                isset($_POST['sold']) ? 1 : 0,
                $id,
                $userId,
            ]);
        }
    }

    elseif ($action === 'remove_vehicle') {
        $stmt = $db->prepare("DELETE FROM vehicles WHERE id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)");
        $stmt->execute([(int)$_POST['id'], $userId]);
    }

    elseif ($action === 'toggle_sold') {
        $stmt = $db->prepare("UPDATE vehicles SET sold = NOT sold WHERE id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)");
        $stmt->execute([(int)$_POST['id'], $userId]);
    }


    $tab = $_POST['tab'] ?? 'dashboard';
    header("Location: index.php?tab=$tab");
    exit;
}

// ─── FETCH DATA (filtered by active auction) ─────────────────────────────────
$members  = $userId
    ? (function() use ($db, $userId) { $q = $db->prepare("SELECT * FROM members WHERE user_id=? ORDER BY id"); $q->execute([$userId]); return $q->fetchAll(); })()
    : [];
$vehicles = $activeAuctionId
    ? (function() use ($db, $activeAuctionId, $userId) { $q = $db->prepare("SELECT v.* FROM vehicles v JOIN members m ON v.member_id = m.id WHERE v.auction_id=? AND m.user_id=? ORDER BY v.id"); $q->execute([$activeAuctionId, $userId]); return $q->fetchAll(); })()
    : [];



// ─── CALC STATEMENT ──────────────────────────────────────────────────────────
// ─── ACTIVE TAB & STATS ───────────────────────────────────────────────────────
$tab      = $_GET['tab'] ?? 'dashboard';
$tabs     = ['dashboard'=>['icon'=>'📊','label'=>'Dashboard'],'members'=>['icon'=>'👥','label'=>'Members'],'vehicles'=>['icon'=>'🚗','label'=>'Vehicles'],'statements'=>['icon'=>'📄','label'=>'Statements']];
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
<link rel="stylesheet" href="css/style.css?v=2.4">
<?php include 'css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen flex flex-col"><div class="flex-1 flex flex-col">

<!-- ─── TOP BAR ─────────────────────────────────────── -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-6 sticky top-0 z-50 animate-slide-down topbar-inner">
  <div class="shrink-0">
    <div class="text-ak-gold font-bold text-lg tracking-tight">⚡ AuctionKai <span class="text-[10px] bg-ak-border text-ak-muted px-2 py-0.5 rounded ml-1 font-mono">MySQL</span></div>
    <div class="text-ak-muted text-[11px]">Settlement Management System</div>
  </div>
  <?php if ($auction): ?>
  <div class="flex items-center gap-2 flex-1 justify-center">
    <form onsubmit="return submitSaveAuction(event)" style='display:contents' data-parsley-validate>
      <div class="flex items-center gap-2 flex-wrap">
        <input class="inp w-56" name="name" value="<?= h($auction['name']) ?>" placeholder="Auction name">
        <input class="inp w-36 opacity-50 cursor-not-allowed" type="date" name="date" value="<?= h($auction['date']) ?>" disabled>
        <div class="flex items-center gap-1"><span class="text-ak-muted text-[11px]">Commission</span><input class="inp font-mono w-16" type="number" step="1" name="commissionFee" value="<?= (float)($auction['commission_fee'] ?? 3300) ?>" data-parsley-type="number" data-parsley-min="0"><span class="text-ak-muted text-[11px]">¥/member</span></div>
        <button class="btn btn-dark btn-sm" type="submit">Save</button>
        <a class="btn btn-sm" href="delete_auction.php?auction_id=<?= (int)$auction['id'] ?>" style="background:rgba(204,119,119,.15);color:var(--red);border:1px solid rgba(204,119,119,.3)">🗑 Delete</a>
      </div>
    </form>
  </div>
  <?php endif; ?>
  <div class="flex items-center gap-3 shrink-0 ml-auto">
    <a href="profile.php" class="flex items-center gap-2 no-underline hover:opacity-80 transition-opacity">
      <div class="w-8 h-8 rounded-full bg-ak-gold text-ak-bg flex items-center justify-center font-bold text-sm"><?= mb_strtoupper(mb_substr($userName, 0, 1)) ?></div>
      <div><div class="text-ak-text text-sm font-semibold"><?= h($userName) ?></div><div class="text-ak-muted text-[10px] capitalize"><?= h($userRole) ?></div></div>
    </a>
    <?php if (!empty($_SESSION['original_admin_id'])): ?>
      <form method="POST" action="admin.php" style="display:inline" data-parsley-validate>
        <input type="hidden" name="action" value="return_to_admin">
        <input type="hidden" name="_tok" value="<?= h($tok) ?>">
        <button type="submit" class="text-[11px] font-bold px-3 py-1.5 rounded-lg bg-ak-gold/20 text-ak-gold border border-ak-gold/30 hover:bg-ak-gold/30 transition-colors">← Return to Admin Panel</button>
      </form>
    <?php endif; ?>
    <?php if ($userRole === 'admin'): ?>
      <a href="admin.php" class="text-ak-muted text-xs hover:text-ak-gold transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">⚙️ Admin</a>
    <?php endif; ?>
    <button onclick="KeyboardShortcuts.openShortcutsModal()" class="theme-toggle" title="Keyboard shortcuts (?)"><span>⌨</span><span class="hide-mobile">Shortcuts</span></button>
    <a href="auth/logout.php" class="text-ak-muted text-xs hover:text-ak-red transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">Logout</a>
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
    <button class="px-3 py-2 rounded-lg border border-dashed border-ak-border text-ak-muted text-xs hover:border-ak-gold hover:text-ak-gold transition-all duration-200" onclick="document.getElementById('addAuctionForm').classList.toggle('hidden')">+ New Auction</button>
  </div>
  <?php if ($auction): ?>
  <div class="text-ak-muted text-xs mt-2">
    <b class="text-ak-text"><?= h($auction['name']) ?></b> · <?= h($auction['date']) ?> · Commission: ¥<?= number_format((float)($auction['commission_fee'] ?? 3300)) ?>/member · Expires: <?= h($auction['expires_at'] ?? 'N/A') ?>
  </div>
  <?php endif; ?>
</div>

<!-- ─── ADD AUCTION FORM ─────────────────────────────── -->
<div id="addAuctionForm" class="hidden bg-ak-bg2 border-b border-ak-border px-7 py-4 animate-slide-down">
  <form onsubmit="return submitAddAuction(event)" data-parsley-validate>
    <div class="add-row ar-auction mb-0">
      <div><label class="lbl">Auction Name *</label><input class="inp" name="name" placeholder="e.g. Tokyo Bay Auto Auction" data-parsley-required="true"></div>
      <div><label class="lbl">Auction Date *</label><input class="inp" type="date" name="date" data-parsley-required="true"></div>
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

<?php elseif ($tab === 'dashboard'): ?>
<?php
$totalGross = 0; $totalNet = 0; $memberRanking = [];
foreach ($members as $m) {
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300));
    $totalGross += $s['grossSales'];
    $totalNet  += $s['netPayout'];
    $memberRanking[] = ['name'=>$m['name'], 'count'=>$s['count'], 'unsoldCount'=>$s['unsoldCount'], 'gross'=>$s['grossSales'], 'net'=>$s['netPayout']];
}
usort($memberRanking, fn($a, $b) => $b['net'] <=> $a['net']);
?>
<h2 class="text-lg font-bold mb-5">Dashboard — <?= h($auction['name']) ?></h2>

<!-- Stats Cards -->
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Members</div>
    <div class="text-3xl font-bold text-ak-text mt-2 font-mono"><?= count($members) ?></div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.05s">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Vehicles</div>
    <div class="text-3xl font-bold text-ak-text mt-2 font-mono"><?= count($vehicles) ?></div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.1s">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Sold</div>
    <div class="text-3xl font-bold text-ak-green mt-2 font-mono"><?= $totalSold ?></div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.15s">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Gross Sales</div>
    <div class="text-2xl font-bold text-ak-text2 mt-2 font-mono"><?= fmt($totalGross) ?></div>
  </div>
  <div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.2s">
    <div class="text-ak-muted text-[10px] font-bold tracking-[2px] uppercase">Net Payout</div>
    <div class="text-2xl font-bold text-ak-gold mt-2 font-mono"><?= fmt($totalNet) ?></div>
  </div>
</div>

<!-- Member Ranking -->
<div class="bg-ak-card border border-ak-border rounded-xl p-5 animate-fade-in-up" style="animation-delay:.25s">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-4">Member Ranking by Net Payout</div>
  <?php if (empty($memberRanking) || $totalNet == 0): ?>
    <div class="text-ak-muted text-center py-8">No sales data available yet.</div>
  <?php else: ?>
  <div class="flex flex-col gap-2">
    <?php foreach ($memberRanking as $i => $mr): ?>
    <?php if ($mr['net'] <= 0 && $mr['gross'] <= 0) continue; ?>
    <div class="flex items-center gap-4 bg-ak-bg rounded-lg px-4 py-3">
      <div class="w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm <?= $i === 0 ? 'bg-ak-gold text-ak-bg' : 'bg-ak-border text-ak-muted' ?>"><?= $i + 1 ?></div>
      <div class="flex-1 min-w-0">
        <div class="text-ak-text font-semibold"><?= h($mr['name']) ?></div>
        <div class="text-ak-muted text-xs"><?= $mr['count'] ?> sold · <?= $mr['unsoldCount'] ?> unsold · Gross: <?= fmt($mr['gross']) ?></div>
      </div>
      <div class="text-ak-gold font-mono font-bold text-lg"><?= fmt($mr['net']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php elseif ($tab === 'members'): ?>
<h2 class="text-lg font-bold mb-5">Members / Sellers — <?= h($auction['name']) ?></h2>
<div class="bg-ak-card rounded-xl p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Add New Member</div>
  <form onsubmit="return submitAddMember(event)" data-parsley-validate>
    <div class="grid grid-cols-4 gap-3">
      <div><label class="lbl">Full Name *</label><input class="inp" name="name" placeholder="e.g. Ahmad Hassan" data-parsley-required="true"></div>
      <div><label class="lbl">Phone</label><input class="inp" name="phone" placeholder="090-xxxx-xxxx"></div>
      <div><label class="lbl">Email</label><input class="inp" type="email" name="email" placeholder="email@example.com" data-parsley-type="email"></div>
      <div class="flex items-end pt-[22px]"><button class="btn btn-gold" type="submit">+ Add</button></div>
    </div>
  </form>
</div>
<div class="mb-4">
  <input class="inp max-w-md" id="memberListSearch" placeholder="🔍 Search members by name, phone, or email…" oninput="filterMemberList()">
</div>
<div class="flex flex-col gap-2.5" id="memberList">
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
  <div class="bg-ak-card rounded-xl p-4 border border-ak-border flex items-center gap-4 hover:border-ak-border/80 transition-all duration-200 animate-fade-in-up member-card" data-member-name="<?= h(mb_strtolower($m['name'])) ?>" data-member-phone="<?= h(mb_strtolower($m['phone'])) ?>" data-member-email="<?= h(mb_strtolower($m['email'])) ?>">
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
      <button class="btn btn-ghost btn-sm" onclick="removeMember(<?= (int)$m['id'] ?>, '<?= h(addslashes($m['name'])) ?>')">Remove</button>
    </div>
  </div>
  <?php endif; ?>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<?php elseif ($tab === 'vehicles'): ?>
<h2 class="text-lg font-bold mb-5">Vehicle Listings — <?= h($auction['name']) ?></h2>
<div class="mb-4">
  <input class="inp max-w-md" id="vehicleSearch" placeholder="🔍 Search by lot, member, make, model…" oninput="filterVehicles()">
</div>
<div class="bg-ak-card rounded-xl p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Add Vehicle</div>
  <form id="addVehicleForm" onsubmit="return submitAddVehicle(event)" data-parsley-validate>
    <div class="grid grid-cols-6 gap-2 add-vehicle-grid" id="addVehicleFields">
      <div class="col-span-2 relative">
        <label class="lbl">Member *</label>
        <input class="inp" id="memberSearch" name="memberSearch" placeholder="Type to search member…" autocomplete="off" data-parsley-required="true" data-parsley-required-message="Member is required" onfocus="showMemberResults()" oninput="filterMembers()">
        <input type="hidden" id="memberId" name="memberId">
        <div id="memberDropdown" class="member-dropdown" style="display:none"></div>
      </div>
      <div><label class="lbl">Make *</label><input class="inp" id="add_make" name="make" placeholder="Toyota" data-parsley-required="true" data-parsley-required-message="Make is required"></div>
      <div><label class="lbl">Model</label><input class="inp" id="add_model" name="model" placeholder="Prius"></div>
      <div><label class="lbl">Lot #</label><input class="inp" id="add_lot" name="lot" placeholder="A-001" data-parsley-minlength="1"></div>
      <div><label class="lbl">Sold Price (¥) *</label><input class="inp font-mono sold-fields" type="number" id="add_soldPrice" name="soldPrice" placeholder="850000" data-parsley-type="number" data-parsley-min="0"></div>
      <div><label class="lbl">Recycle Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_recycleFee" name="recycleFee" placeholder="15000" data-parsley-type="number" data-parsley-min="0"></div>
      <div><label class="lbl">Listing Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_listingFee" name="listingFee" placeholder="3000" data-parsley-type="number" data-parsley-min="0"></div>
      <div><label class="lbl">Sold Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_soldFee" name="soldFee" placeholder="25500" data-parsley-type="number" data-parsley-min="0"></div>
      <div class="nagare-field"><label class="lbl">Nagare Fee (¥)</label><input class="inp font-mono" type="number" id="add_nagareFee" name="nagareFee" placeholder="8000" data-parsley-type="number" data-parsley-min="0" disabled></div>
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
<div class="bg-ak-card rounded-xl border border-ak-border overflow-x-auto vehicles-table-desktop">
  <table class="vt">
    <thead><tr><th>Lot #</th><th>Member</th><th>Vehicle</th><th class="r">Sold Price</th><th class="r">Tax 10%</th><th class="r">Recycle</th><th class="r">Listing</th><th class="r">Sold Fee</th><th class="r">Nagare</th><th class="r">Total</th><th>Status</th><th class="w-[90px]">Actions</th></tr></thead>
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
        <td class="text-right font-mono font-bold <?= $v['sold'] ? 'text-ak-gold' : 'text-ak-muted' ?>" data-field="total">
          <?php if ($v['sold']): $vTotal = (float)$v['sold_price'] + round((float)$v['sold_price'] * 0.10) + (float)($v['recycle_fee'] ?? 0) - (float)($v['listing_fee'] ?? 0) - (float)($v['sold_fee'] ?? 0) - (float)($v['nagare_fee'] ?? 0); ?>
          <?= fmt($vTotal) ?>
          <?php else: ?>—<?php endif; ?>
        </td>
        <td data-field="status">
          <button class="sb <?= $v['sold'] ? 'sy' : 'sn' ?>" onclick="toggleSold(<?= (int)$v['id'] ?>, this)"><?= $v['sold'] ? '✓ SOLD' : '✗ UNSOLD' ?></button>
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

<!-- Mobile Card View -->
<div class="vehicle-card-mobile" id="vehicle-cards-mobile">
<?php foreach ($vehicles as $v):
  $owner = array_values(array_filter($members, fn($m) => (int)$m['id'] === (int)$v['member_id']))[0] ?? null;
?>
<div class="v-card" id="v-card-<?= (int)$v['id'] ?>">
  <div class="v-card-top">
    <span class="v-card-lot"><?= h($v['lot'] ?: '—') ?></span>
    <button onclick="toggleSold(<?= (int)$v['id'] ?>)" class="sb <?= $v['sold'] ? 'sy' : 'sn' ?>" id="sold-btn-m-<?= (int)$v['id'] ?>"><?= $v['sold'] ? '✓ SOLD' : '✗ UNSOLD' ?></button>
  </div>
  <div class="v-card-name"><?= h($v['make'] . ' ' . $v['model']) ?></div>
  <div class="v-card-meta"><?= h($owner['name'] ?? '?') ?></div>
  <div class="v-card-fees">
    <div class="v-card-fee">
      <div class="v-card-fee-label">Sold Price</div>
      <div class="v-card-fee-value <?= $v['sold'] ? '' : 'muted' ?>"><?= $v['sold'] ? fmt((float)$v['sold_price']) : '—' ?></div>
    </div>
    <div class="v-card-fee">
      <div class="v-card-fee-label">Recycle Fee</div>
      <div class="v-card-fee-value"><?= fmt((float)$v['recycle_fee']) ?></div>
    </div>
    <div class="v-card-fee">
      <div class="v-card-fee-label">Listing Fee</div>
      <div class="v-card-fee-value"><?= fmt((float)$v['listing_fee']) ?></div>
    </div>
    <div class="v-card-fee">
      <div class="v-card-fee-label">Sold Fee</div>
      <div class="v-card-fee-value"><?= fmt((float)$v['sold_fee']) ?></div>
    </div>
    <div class="v-card-fee">
      <div class="v-card-fee-label">Nagare Fee</div>
      <div class="v-card-fee-value <?= !$v['sold'] ? '' : 'muted' ?>"><?= !$v['sold'] ? fmt((float)$v['nagare_fee']) : '—' ?></div>
    </div>
  </div>
  <div class="v-card-actions">
    <button onclick="openEditModal(<?= (int)$v['id'] ?>)" style="background:#1E3A5F;color:#D4A84B">✎ Edit</button>
    <button onclick="deleteVehicle(<?= (int)$v['id'] ?>)" style="background:#3A1A1A;color:#CC7777">× Delete</button>
  </div>
</div>
<?php endforeach; ?>
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
        <?php if ($s['commissionTotal'] > 0): ?>
        <div class="dr"><span class="dr-l">Commission ¥<?= number_format($s['commissionFee']) ?>/member</span><span class="dr-a">−<?= fmt($s['commissionTotal']) ?></span></div>
        <?php endif; ?>
        <div class="dt"><span class="dt-l">Total Deductions</span><span class="dt-n">−<?= fmt($s['totalDed']) ?></span></div>
        <div class="np"><span class="np-l">NET PAYOUT / お支払い額</span><span class="np-n"><?= fmt($s['netPayout']) ?></span></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
<?php if (!$hasSales): ?>
  <div class="bg-ak-card rounded-xl p-8 text-center text-ak-muted border border-ak-border">No sold vehicles recorded for this auction yet.</div>
<?php endif; ?>
<?php endif; ?>

<?php endif; ?>
<script>const membersData = <?= json_encode(array_map(fn($m) => ['id'=>(int)$m['id'], 'name'=>$m['name'], 'phone'=>$m['phone']], $members)) ?>;const activeAuctionId = <?= (int)$activeAuctionId ?>;</script>
<script src="js/app.js?v=2.4"></script>

<!-- Edit Vehicle Modal -->
<div id="editModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center hidden" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[720px] max-h-[90vh] overflow-y-auto p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-ak-gold text-lg font-bold">Edit Vehicle</h3>
      <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeEditModal()">×</button>
    </div>
    <div id="modalMsg" class="hidden mb-3 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
    <form id="editForm" onsubmit="return submitEditForm(event)" data-parsley-validate>
      <input type="hidden" id="edit_id" name="id">
      <div class="grid grid-cols-2 gap-3 max-[600px]:grid-cols-1">
        <div class="relative">
          <label class="lbl">Member *</label>
          <input class="inp" id="edit_memberSearch" placeholder="Type to search member…" autocomplete="off" oninput="filterModalMembers()" data-parsley-required="true">
          <input type="hidden" id="edit_memberId" name="memberId">
          <div id="edit_memberDropdown" class="member-dropdown" style="display:none"></div>
        </div>
        <div><label class="lbl">Make *</label><input class="inp" id="edit_make" name="make" data-parsley-required="true"></div>
        <div><label class="lbl">Model</label><input class="inp" id="edit_model" name="model"></div>
        <div><label class="lbl">Lot #</label><input class="inp" id="edit_lot" name="lot"></div>
        <div><label class="lbl">Sold Price (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_soldPrice" name="soldPrice" data-parsley-type="number" data-parsley-min="0"></div>
        <div><label class="lbl">Recycle Fee (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_recycleFee" name="recycleFee" data-parsley-type="number" data-parsley-min="0"></div>
        <div><label class="lbl">Listing Fee (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_listingFee" name="listingFee" data-parsley-type="number" data-parsley-min="0"></div>
        <div><label class="lbl">Sold Fee (¥)</label><input class="inp font-mono modal-sold-field" type="number" id="edit_soldFee" name="soldFee" data-parsley-type="number" data-parsley-min="0"></div>
        <div class="modal-nagare-field"><label class="lbl">Nagare Fee (¥)</label><input class="inp font-mono" type="number" id="edit_nagareFee" name="nagareFee" data-parsley-type="number" data-parsley-min="0" disabled></div>
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
    <form id="editMemberForm" onsubmit="return submitEditMember(event)" data-parsley-validate>
      <input type="hidden" id="em_id" name="id">
      <div class="mb-4"><label class="lbl">Full Name *</label><input class="inp" id="em_name" name="name" data-parsley-required="true"></div>
      <div class="mb-4"><label class="lbl">Phone</label><input class="inp" id="em_phone" name="phone" placeholder="090-xxxx-xxxx"></div>
      <div class="mb-5"><label class="lbl">Email</label><input class="inp" type="email" id="em_email" name="email" placeholder="email@example.com" data-parsley-type="email"></div>
      <div class="flex justify-end gap-2 pt-4 border-t border-ak-border">
        <button type="button" class="btn btn-dark btn-sm" onclick="closeEditMemberModal()">Cancel</button>
        <button type="submit" class="btn btn-gold btn-sm" id="emSubmitBtn">Save</button>
      </div>
    </form>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
<?php require_once 'includes/footer.php'; ?>
<!-- Toast Container -->
<div id="toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:10px;pointer-events:none"></div>

<!-- Keyboard Shortcuts Modal -->
<div class="shortcuts-modal-overlay" id="shortcuts-modal-overlay">
  <div class="shortcuts-modal">
    <h3><span>⌨ Keyboard Shortcuts</span><button onclick="closeShortcutsModal()" style="background:none;border:none;color:#6A88A0;font-size:20px;cursor:pointer;line-height:1">×</button></h3>
    <div class="shortcuts-group">
      <div class="shortcuts-group-title">Navigation</div>
      <div class="shortcut-row"><span>Go to Members tab</span><div class="shortcut-keys"><span class="kbd">G</span><span class="shortcut-plus">then</span><span class="kbd">M</span></div></div>
      <div class="shortcut-row"><span>Go to Vehicles tab</span><div class="shortcut-keys"><span class="kbd">G</span><span class="shortcut-plus">then</span><span class="kbd">V</span></div></div>
      <div class="shortcut-row"><span>Go to Statements tab</span><div class="shortcut-keys"><span class="kbd">G</span><span class="shortcut-plus">then</span><span class="kbd">S</span></div></div>
      <div class="shortcut-row"><span>Go to Dashboard tab</span><div class="shortcut-keys"><span class="kbd">G</span><span class="shortcut-plus">then</span><span class="kbd">D</span></div></div>
    </div>
    <div class="shortcuts-group">
      <div class="shortcuts-group-title">Actions</div>
      <div class="shortcut-row"><span>Add new vehicle</span><div class="shortcut-keys"><span class="kbd">N</span></div></div>
      <div class="shortcut-row"><span>Add new member</span><div class="shortcut-keys"><span class="kbd">Shift</span><span class="shortcut-plus">+</span><span class="kbd">N</span></div></div>
      <div class="shortcut-row"><span>Focus lot number field</span><div class="shortcut-keys"><span class="kbd">L</span></div></div>
      <div class="shortcut-row"><span>Print all PDFs</span><div class="shortcut-keys"><span class="kbd">Ctrl</span><span class="shortcut-plus">+</span><span class="kbd">P</span></div></div>
    </div>
    <div class="shortcuts-group">
      <div class="shortcuts-group-title">General</div>
      <div class="shortcut-row"><span>Show this help</span><div class="shortcut-keys"><span class="kbd">?</span></div></div>
      <div class="shortcut-row"><span>Close modal / dialog</span><div class="shortcut-keys"><span class="kbd">Esc</span></div></div>
      <div class="shortcut-row"><span>Search vehicles</span><div class="shortcut-keys"><span class="kbd">/</span></div></div>
    </div>
    <div style="text-align:center;margin-top:8px;font-size:11px;color:#3A5570">Shortcuts are disabled when typing in input fields</div>
  </div>
</div>
<div class="shortcut-hint" id="shortcut-hint"></div>
</body>
</html>
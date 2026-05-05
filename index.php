<?php
header("Content-Security-Policy: default-src 'self'; connect-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://code.jquery.com https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.tailwindcss.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");
require_once __DIR__ . '/includes/constants.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/activity.php';
require_once __DIR__ . '/includes/maintenance_check.php';
require_once __DIR__ . '/includes/branding.php';
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
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

// ─── MAINTENANCE CHECK ────────────────────────────────────────────────────────
checkMaintenanceMode($db, $userRole);

// ─── BRANDING ────────────────────────────────────────────────────────────────
$brand = loadBranding($db);

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
            if ($date > date('Y-m-d')) {
                // Future dates not allowed
                header('Location: index.php?tab=dashboard');
                exit;
            }
            $expiresAt = date('Y-m-d', strtotime('+14 days'));
            $stmt = $db->prepare("INSERT INTO auction (user_id, name, date, expires_at) VALUES (?,?,?,?)");
            $stmt->execute([$userId, $name, $date, $expiresAt]);
            $newId = (int)$db->lastInsertId();
            $_SESSION['auction_id'] = $newId;
            logActivity($db, $userId, 'auction.create', 'auction', $newId, "Created auction: " . $name);
        }
    }

    elseif ($action === 'delete_auction') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM auction WHERE id=? AND user_id=?")->execute([$id, $userId]);
        logActivity($db, $userId, 'auction.delete', 'auction', $id, "Deleted auction ID: " . $id);
        unset($_SESSION['auction_id']);
    }

    elseif ($action === 'save_auction') {
        $stmt = $db->prepare("UPDATE auction SET name=?, date=?, commission_fee=? WHERE id=? AND user_id=?");
        $stmt->execute([trim($_POST['name']), trim($_POST['date']), (float)($_POST['commissionFee'] ?? 3.00), $activeAuctionId, $userId]);
        logActivity($db, $userId, 'auction.update', 'auction', $activeAuctionId, "Updated auction: " . trim($_POST['name']));
    }

    elseif ($action === 'add_member') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $dup = $db->prepare("SELECT id FROM members WHERE user_id=? AND name=?");
            $dup->execute([$userId, $name]);
            if (!$dup->fetch()) {
                $stmt = $db->prepare("INSERT INTO members (user_id, name, phone, email) VALUES (?,?,?,?)");
                $stmt->execute([$userId, $name, trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? '')]);
                logActivity($db, $userId, 'member.add', 'member', (int)$db->lastInsertId(), "Added member: " . $name);
            }
        }
    }

    elseif ($action === 'update_member') {
        $id   = (int)$_POST['id'];
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $db->prepare("UPDATE members SET name=?, phone=?, email=? WHERE id=? AND user_id=?");
            $stmt->execute([$name, trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? ''), $id, $userId]);
            logActivity($db, $userId, 'member.update', 'member', $id, "Updated member: " . $name);
        }
    }

    elseif ($action === 'remove_member') {
        $stmt = $db->prepare("DELETE FROM members WHERE id=? AND user_id=?");
        $stmt->execute([(int)$_POST['id'], $userId]);
        logActivity($db, $userId, 'member.remove', 'member', (int)$_POST['id'], "Removed member ID: " . (int)$_POST['id']);
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
            logActivity($db, $userId, 'vehicle.add', 'vehicle', (int)$db->lastInsertId(), "Added vehicle: " . $make . " " . trim($_POST['model'] ?? '') . " lot " . trim($_POST['lot'] ?? ''));
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
            logActivity($db, $userId, 'vehicle.update', 'vehicle', $id, "Updated vehicle ID: " . $id);
        }
    }

    elseif ($action === 'remove_vehicle') {
        $stmt = $db->prepare("DELETE FROM vehicles WHERE id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)");
        $stmt->execute([(int)$_POST['id'], $userId]);
        logActivity($db, $userId, 'vehicle.delete', 'vehicle', (int)$_POST['id'], "Deleted vehicle ID: " . (int)$_POST['id']);
    }

    elseif ($action === 'toggle_sold') {
        $stmt = $db->prepare("UPDATE vehicles SET sold = NOT sold WHERE id=? AND auction_id IN (SELECT id FROM auction WHERE user_id=?)");
        $stmt->execute([(int)$_POST['id'], $userId]);
        logActivity($db, $userId, 'vehicle.sold', 'vehicle', (int)$_POST['id'], "Toggled sold status vehicle ID: " . (int)$_POST['id']);
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

// Fetch payment statuses for active auction
$paymentStatuses = [];
if ($activeAuctionId) {
    $psq = $db->prepare("SELECT member_id, status, paid_amount, paid_at, notes FROM payment_status WHERE auction_id = ?");
    $psq->execute([$activeAuctionId]);
    foreach ($psq->fetchAll() as $ps) {
        $paymentStatuses[$ps['member_id']] = $ps;
    }
}

// Fetch statement history for active auction
$stmtHistory = [];
if ($activeAuctionId) {
    $shq = $db->prepare("SELECT sh.*, m.name as member_name FROM statement_history sh JOIN members m ON sh.member_id = m.id WHERE sh.auction_id = ? AND sh.user_id = ? ORDER BY sh.created_at DESC LIMIT 100");
    $shq->execute([$activeAuctionId, $userId]);
    foreach ($shq->fetchAll() as $row) {
        $stmtHistory[$row['member_id']][] = $row;
    }
}

// Fetch special member fees for this auction
$memberFeesAll = [];
if ($activeAuctionId) {
    $mfq = $db->prepare("SELECT * FROM member_fees WHERE auction_id = ? ORDER BY member_id, created_at ASC");
    $mfq->execute([$activeAuctionId]);
    foreach ($mfq->fetchAll() as $mf) {
        $memberFeesAll[$mf['member_id']][] = $mf;
    }
}



// ─── CALC STATEMENT ──────────────────────────────────────────────────────────
// ─── ACTIVE TAB & STATS ───────────────────────────────────────────────────────
$tab      = $_GET['tab'] ?? 'dashboard';
$tabs     = ['dashboard'=>['icon'=>'📊','label'=>'Dashboard'],'members'=>['icon'=>'👥','label'=>'Members'],'vehicles'=>['icon'=>'🚗','label'=>'Vehicles'],'special_fees'=>['icon'=>'💴','label'=>'Fees'],'statements'=>['icon'=>'📄','label'=>'Statements']];
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
<link rel="stylesheet" href="css/style.css?v=3.5">
<?php include 'css/tailwind-config.php'; ?>
<style>
:root {
  --ak-gold: <?= sanitizeColor($brand['brand_accent_color']) ?>;
  --ak-gold-rgb: <?php $hex = ltrim(sanitizeColor($brand['brand_accent_color']), '#'); echo hexdec(substr($hex,0,2)) . ', ' . hexdec(substr($hex,2,2)) . ', ' . hexdec(substr($hex,4,2)); ?>;
}
</style>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen flex flex-col"><div class="flex-1 flex flex-col">

<!-- ─── TOP BAR ─────────────────────────────────────── -->
<div class="bg-ak-bg2 border-b border-ak-border px-7 py-3 flex items-center gap-6 sticky top-0 z-50 animate-slide-down topbar-inner">
  <div class="shrink-0">
    <div class="text-ak-gold font-bold text-lg tracking-tight">⚡ <?= h($brand['brand_name']) ?></div>
    <div class="text-ak-muted text-[11px]"><?= h($brand['brand_tagline']) ?></div>
  </div>
  <?php if ($auction): ?>
  <div class="flex items-center gap-2 flex-1 justify-center">
    <form onsubmit="return submitSaveAuction(event)" style='display:contents' data-parsley-validate>
      <div class="flex items-center gap-2 flex-wrap">
        <input class="inp w-56" name="name" value="<?= h($auction['name']) ?>" placeholder="Auction name">
        <input class="inp w-36 opacity-50 cursor-not-allowed" type="date" name="date" value="<?= h($auction['date']) ?>" disabled>
        <div class="flex items-center gap-1"><span class="text-ak-muted text-[11px]">Commission</span><input class="inp font-mono w-16" type="number" step="1" name="commissionFee" value="<?= (float)($auction['commission_fee'] ?? 3300) ?>" data-parsley-type="number" data-parsley-min="0"><span class="text-ak-muted text-[11px]">¥/member</span></div>
        <button class="btn btn-dark btn-sm" type="submit">Save</button>
        <a class="btn btn-sm" href="api/delete_auction.php?auction_id=<?= (int)$auction['id'] ?>" style="background:rgba(204,119,119,.15);color:var(--red);border:1px solid rgba(204,119,119,.3)">🗑 Delete</a>
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
      <?php
        $impStart = (int)($_SESSION['impersonate_started'] ?? 0);
        $impRemaining = max(0, 3600 - (time() - $impStart));
        $impMins = floor($impRemaining / 60);
      ?>
      <form method="POST" action="admin/index.php" style="display:inline" data-parsley-validate>
        <input type="hidden" name="action" value="return_to_admin">
        <input type="hidden" name="_tok" value="<?= h($tok) ?>">
        <button type="submit" class="text-[11px] font-bold px-3 py-1.5 rounded-lg bg-ak-gold/20 text-ak-gold border border-ak-gold/30 hover:bg-ak-gold/30 transition-colors">← Return to Admin (<?= $impMins ?>m left)</button>
      </form>
    <?php endif; ?>
    <?php if ($userRole === 'admin'): ?>
      <a href="admin/index.php" style="background:#1A3A2A;color:#4CAF82;border:1px solid #2A5A3A;border-radius:8px;padding:6px 14px;font-size:12px;font-weight:700;text-decoration:none">⚙ Admin</a>
    <?php endif; ?>
    <button onclick="KeyboardShortcuts.openShortcutsModal()" class="theme-toggle" title="Keyboard shortcuts (?)"><span>⌨</span><span class="hide-mobile">Shortcuts</span></button>
    <a href="auth/logout.php" class="text-ak-muted text-xs hover:text-ak-red transition-colors px-3 py-2 rounded-lg hover:bg-ak-infield">Logout</a>
  </div>
</div>

<?php
$maintenanceOn = false;
try {
    $maintenanceOn = $db->query("SELECT value FROM settings WHERE `key`='maintenance_mode'")->fetchColumn() === '1';
} catch (Exception $e) {}
if ($maintenanceOn && $userRole === 'admin'):
?>
<div class="bg-yellow-500/20 border-b border-yellow-500/40 px-7 py-2 flex items-center justify-between gap-4">
  <div class="flex items-center gap-2">
    <span class="text-yellow-400 font-bold animate-pulse">🚧</span>
    <span class="text-yellow-400 text-sm font-semibold">Maintenance Mode is ACTIVE — Non-admin users cannot access the system</span>
  </div>
  <a href="admin/index.php?tab=maintenance" class="text-yellow-400 text-xs hover:underline font-medium">Manage →</a>
</div>
<?php endif; ?>

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
        <span class="text-[10px] px-1.5 py-0.5 rounded font-bold <?= (int)$a['id'] === $activeAuctionId ? 'bg-ak-bg/20 text-ak-bg' : $badgeClass ?>"><?= $badgeText ?></span>
      </a>
    <?php endforeach; ?>
    <button class="px-3 py-2 rounded-lg border border-dashed border-ak-border text-ak-muted text-xs hover:border-ak-gold hover:text-ak-gold transition-all duration-200" onclick="document.getElementById('addAuctionForm').classList.toggle('hidden')">+ New Auction</button>
  </div>
  <?php if ($auction): ?>
  <div class="text-ak-muted text-xs mt-2">
    <b class="text-ak-text"><?= h($auction['name']) ?></b> · <?= h($auction['date']) ?> · Commission: ¥<?= number_format((float)($auction['commission_fee'] ?? 3300)) ?>/member · <span class="<?= $daysLeft <= 3 ? 'text-yellow-400 font-semibold' : 'text-ak-text2' ?>">Expires: <?= h($auction['expires_at'] ?? 'N/A') ?></span>
  </div>
  <?php endif; ?>
</div>

<!-- ─── ADD AUCTION FORM ─────────────────────────────── -->
<div id="addAuctionForm" class="hidden bg-ak-bg2 border-b border-ak-border px-7 py-4 animate-slide-down">
  <form onsubmit="return submitAddAuction(event)" data-parsley-validate class="max-w-md">
    <div class="add-row ar-auction mb-0" style="grid-template-columns:1fr 1fr auto">
      <div><label class="lbl">Auction Name *</label><input class="inp" name="name" placeholder="e.g. Tokyo Bay Auto Auction" data-parsley-required="true"></div>
      <div><label class="lbl">Auction Date *</label><input class="inp" type="date" name="date" data-parsley-required="true" max="<?= date('Y-m-d') ?>"></div>
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
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300), $memberFeesAll[$m['id']] ?? []);
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
      <div class="flex items-end pt-[22px]"><button class="btn btn-gold" type="submit" id="addMemberBtn">+ Add</button></div>
    </div>
  </form>
  <div id="addMemberMsg" class="hidden mt-2.5 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
</div>

<!-- CSV Import Card -->
<div class="bg-ak-card rounded-xl p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Bulk Import via CSV</div>
  <div class="flex items-start gap-4 flex-wrap">
    <div class="flex-1 min-w-[200px]">
      <div class="text-ak-text2 text-sm mb-2">Upload a CSV file to import multiple members at once.</div>
      <div class="text-ak-muted text-xs leading-relaxed">Supported columns: <span class="font-mono text-ak-gold">name, phone, email</span><br>First row can be a header row — auto-detected.<br>Duplicates are automatically skipped.</div>
    </div>
    <div class="flex flex-col gap-2 shrink-0">
      <a href="api/csv_template.php" class="btn btn-dark btn-sm text-center">↓ Download Template</a>
      <label class="btn btn-dark btn-sm cursor-pointer text-center" for="csvImportInput">📁 Choose File</label>
      <input type="file" id="csvImportInput" accept=".csv,.txt" class="sr-only" onchange="showCsvFileName(this)">
      <button id="csvImportBtn" class="btn btn-gold btn-sm opacity-50 cursor-not-allowed" disabled onclick="handleCsvImport(document.getElementById('csvImportInput'))">↑ Import CSV</button>
    </div>
    <div id="csvFileName" class="text-ak-muted text-xs mt-2 hidden">📄 <span id="csvFileNameText" class="text-ak-gold font-mono"></span></div>
  </div>
  <div id="csvImportResult" class="hidden mt-3 p-3 rounded-lg text-sm border"></div>
</div>

<!-- Search + Controls -->
<div class="vehicles-search-wrap">
  <div class="search-icon-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="member-search" class="vehicles-search-input" placeholder="Search members by name, phone, or email..." autocomplete="off">
  </div>
  <div class="per-page-wrap">
    <span>Show</span>
    <select class="per-page-select" id="member-per-page-select">
      <option value="10">10</option>
      <option value="25" selected>25</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
    <span>per page</span>
  </div>
  <div class="vehicles-count-badge" id="members-count-badge">— members</div>
</div>

<!-- Members List -->
<div class="flex flex-col gap-2.5" id="members-list-container">
  <!-- populated by JS -->
</div>

<!-- Pagination -->
<div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden mt-3" id="members-pagination-wrap" style="display:none">
  <div class="pagination-wrap">
    <div class="pagination-info" id="members-pagination-info"></div>
    <div class="pagination-controls" id="members-pagination-controls"></div>
  </div>
</div>
</div>

<?php elseif ($tab === 'vehicles'): ?>
<h2 class="text-lg font-bold mb-5">Vehicle Listings — <?= h($auction['name']) ?></h2>

<!-- Add Vehicle Form -->
<div class="bg-ak-card rounded-xl p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Add Vehicle</div>
  <form id="addVehicleForm" onsubmit="return submitAddVehicle(event)" data-parsley-validate>
    <div class="grid grid-cols-6 gap-2 add-vehicle-grid" id="addVehicleFields">
      <div class="col-span-2 relative">
        <label class="lbl">Member *</label>
        <input class="inp" id="memberSearch" name="memberSearch" placeholder="Type to search member…" autocomplete="off" data-parsley-required="true" onfocus="showMemberResults()" oninput="filterMembers()">
        <input type="hidden" id="memberId" name="memberId">
        <div id="memberDropdown" class="member-dropdown" style="display:none"></div>
      </div>
      <div><label class="lbl">Make *</label><input class="inp" id="add_make" name="make" placeholder="Toyota" data-parsley-required="true"></div>
      <div><label class="lbl">Model</label><input class="inp" id="add_model" name="model" placeholder="Prius"></div>
      <div><label class="lbl">Lot #</label><input class="inp" id="add_lot" name="lot" placeholder="A-001"></div>
      <div><label class="lbl">Sold Price (¥) *</label><input class="inp font-mono sold-fields" type="number" id="add_soldPrice" name="soldPrice" placeholder="850000" data-parsley-type="number" data-parsley-min="0"></div>
      <div><label class="lbl">Recycle Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_recycleFee" name="recycleFee" placeholder="15000" data-parsley-type="number" data-parsley-min="0"></div>
      <div><label class="lbl">Listing Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_listingFee" name="listingFee" placeholder="3000" data-parsley-type="number" data-parsley-min="0"></div>
      <div><label class="lbl">Sold Fee (¥)</label><input class="inp font-mono sold-fields" type="number" id="add_soldFee" name="soldFee" placeholder="25500" data-parsley-type="number" data-parsley-min="0"></div>
      <div class="nagare-field"><label class="lbl">Nagare Fee (¥)</label><input class="inp font-mono" type="number" id="add_nagareFee" name="nagareFee" placeholder="8000" data-parsley-type="number" data-parsley-min="0" disabled></div>
      <div class="flex items-end pt-[22px] gap-2">
        <label class="flex items-center gap-1.5 text-ak-muted text-xs cursor-pointer"><input type="checkbox" id="add_sold" name="sold" checked class="accent-ak-gold" onchange="toggleSoldFields(this.checked)"> Sold</label>
        <button class="btn btn-gold" type="submit" id="addVehicleBtn">Add</button>
      </div>
    </div>
    <div id="addVehicleMsg" class="hidden mt-2.5 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
  </form>
</div>

<!-- Search + Controls Row -->
<div class="vehicles-search-wrap">
  <div class="search-icon-wrap">
    <span class="search-icon">🔍</span>
    <input type="text" id="vehicle-search" class="vehicles-search-input" placeholder="Search lot, make, model, member..." autocomplete="off">
  </div>
  <div class="per-page-wrap">
    <span>Show</span>
    <select class="per-page-select" id="per-page-select">
      <option value="10">10</option>
      <option value="25" selected>25</option>
      <option value="50">50</option>
      <option value="100">100</option>
    </select>
    <span>per page</span>
  </div>
  <div class="vehicles-count-badge" id="vehicles-count-badge">— vehicles</div>
</div>

<!-- Vehicles Table -->
<div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden" id="vehicles-table-wrap">
  <table class="vt vehicles-table-desktop" id="vehicles-table">
    <thead>
      <tr><th>Lot #</th><th>Member</th><th>Vehicle</th><th class="r">Sold Price</th><th class="r">Recycle</th><th class="r">Listing</th><th class="r">Sold Fee</th><th class="r">Nagare</th><th class="r">Total</th><th>Status</th><th class="w-[90px]">Actions</th></tr>
    </thead>
    <tbody id="vehicles-tbody">
      <!-- populated by JS -->
    </tbody>
  </table>

  <!-- Pagination Controls -->
  <div class="pagination-wrap" id="pagination-wrap">
    <div class="pagination-info" id="pagination-info"></div>
    <div class="pagination-controls" id="pagination-controls"></div>
  </div>
</div>

<!-- Mobile cards container -->
<div class="vehicle-card-mobile" id="vehicle-cards-mobile">
  <!-- populated by JS -->
</div>

<?php elseif ($tab === 'special_fees'): ?>
<h2 class="text-lg font-bold mb-5">💴 Special Fees — <?= h($auction['name']) ?></h2>

<!-- Add Special Fee Card -->
<div class="bg-ak-card rounded-xl p-5 mb-5 border border-ak-border animate-fade-in-up">
  <div class="text-[10px] font-bold tracking-[2px] uppercase text-ak-muted mb-3">Add Special Fee</div>
  <form id="addSpecialFeeForm" onsubmit="return submitAddSpecialFee(event)">
    <div class="grid grid-cols-6 gap-2">
      <div class="col-span-2 relative">
        <label class="lbl">Member *</label>
        <input class="inp" id="sf_memberSearch" placeholder="Type to search member…" autocomplete="off" required onfocus="showSfMemberResults()" oninput="filterSfMembers()">
        <input type="hidden" id="sf_memberId" required>
        <div id="sf_memberDropdown" class="member-dropdown" style="display:none"></div>
      </div>
      <div class="col-span-2">
        <label class="lbl">Fee Name *</label>
        <input class="inp" id="sf_feeName" placeholder="e.g. Car Wash Fee" required>
      </div>
      <div>
        <label class="lbl">Amount (¥) *</label>
        <input class="inp font-mono" type="number" id="sf_amount" placeholder="3000" min="1" required>
      </div>
      <div class="flex items-end gap-2 pt-[22px]">
        <select class="inp" id="sf_feeType">
          <option value="deduction">− Deduction</option>
          <option value="addition">+ Addition</option>
        </select>
        <button class="btn btn-gold" type="submit" id="addSpecialFeeBtn">Add</button>
      </div>
    </div>
    <!-- Notes row -->
    <div class="mt-2">
      <label class="lbl">Notes</label>
      <input class="inp" id="sf_notes" placeholder="Notes (optional) — e.g. Invoice #123, Car plate number">
    </div>

    <!-- Quick preset chips below the form -->
    <div class="mt-3 flex flex-wrap gap-1.5">
      <span class="text-[10px] uppercase tracking-wider text-ak-muted self-center mr-1">Quick:</span>
      <?php
      $presets = [
        ['name' => 'Car Wash', 'amount' => 3000, 'type' => 'deduction'],
        ['name' => 'Bank Charges', 'amount' => 500, 'type' => 'deduction'],
        ['name' => 'Storage Fee', 'amount' => 5000, 'type' => 'deduction'],
        ['name' => 'Transport Extra', 'amount' => 10000, 'type' => 'deduction'],
        ['name' => 'Repair Cost', 'amount' => 20000, 'type' => 'deduction'],
        ['name' => 'Inspection', 'amount' => 8000, 'type' => 'deduction'],
        ['name' => 'Key Duplicate', 'amount' => 3500, 'type' => 'deduction'],
        ['name' => 'Bonus', 'amount' => 5000, 'type' => 'addition'],
      ];
      foreach ($presets as $p):
      ?>
      <button type="button"
        class="px-2.5 py-1 rounded-lg text-[11px] font-medium border transition-all
          <?= $p['type'] === 'addition'
            ? 'border-ak-green/40 text-ak-green hover:bg-ak-green/10'
            : 'border-ak-border text-ak-muted hover:border-ak-gold hover:text-ak-gold' ?>"
        onclick="sfSetPreset('<?= h($p['name']) ?>', <?= $p['amount'] ?>, '<?= $p['type'] ?>')">
        <?= $p['type'] === 'addition' ? '+' : '−' ?>
        <?= h($p['name']) ?>
      </button>
      <?php endforeach; ?>
    </div>

    <div id="addSpecialFeeMsg" class="hidden mt-2.5 px-3.5 py-2.5 rounded-lg text-[13px]"></div>
  </form>
</div>

<!-- Records Table (same style as vehicles table) -->
<div class="bg-ak-card rounded-xl border border-ak-border overflow-x-auto">
  <table class="vt">
    <thead>
      <tr>
        <th>Member</th>
        <th>Fee Name</th>
        <th>Notes</th>
        <th>Type</th>
        <th class="r">Amount</th>
        <th>Added</th>
        <th></th>
      </tr>
    </thead>
    <tbody id="specialFeesTableBody">
      <?php
      $allSpecialFees = [];
      foreach ($memberFeesAll as $mId => $fees) {
        $memberName = '';
        foreach ($members as $m) {
          if ((int)$m['id'] === $mId) {
            $memberName = $m['name'];
            break;
          }
        }
        foreach ($fees as $fee) {
          $fee['member_name'] = $memberName;
          $allSpecialFees[] = $fee;
        }
      }
      usort($allSpecialFees, fn($a,$b) => strtotime($b['created_at']) - strtotime($a['created_at']));
      ?>
      <?php if (empty($allSpecialFees)): ?>
      <tr>
        <td colspan="7" class="text-center text-ak-muted py-12">
          No special fees yet for this auction. Use the form above to add fees.
        </td>
      </tr>
      <?php else: ?>
      <?php foreach ($allSpecialFees as $fee):
        $isAdd = $fee['fee_type'] === 'addition';
      ?>
      <tr id="sf-row-<?= (int)$fee['id'] ?>" class="animate-fade-in">
        <td class="font-medium text-ak-text"><?= h($fee['member_name']) ?></td>
        <td class="text-ak-text2"><?= h($fee['fee_name']) ?></td>
        <td class="text-ak-muted text-xs"><?= h($fee['notes'] ?? '—') ?></td>
        <td>
          <span class="text-[11px] px-2 py-0.5 rounded-full font-bold <?= $isAdd ? 'bg-ak-green/15 text-ak-green' : 'bg-ak-red/10 text-ak-red' ?>">
            <?= $isAdd ? '+ Addition' : '− Deduction' ?>
          </span>
        </td>
        <td class="text-right font-mono font-bold <?= $isAdd ? 'text-ak-green' : 'text-ak-red' ?>">
          <?= $isAdd ? '+' : '−' ?>¥<?= number_format((float)$fee['amount']) ?>
        </td>
        <td class="text-ak-muted text-xs font-mono"><?= date('Y-m-d', strtotime($fee['created_at'])) ?></td>
        <td>
          <button class="btn-icon" onclick="sfDeleteFee(<?= (int)$fee['id'] ?>, <?= (int)$fee['member_id'] ?>, <?= (int)$activeAuctionId ?>)">×</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Summary row at bottom -->
  <?php if (!empty($allSpecialFees)):
    $sumDed = array_sum(array_map(fn($f) => $f['fee_type'] === 'deduction' ? (float)$f['amount'] : 0, $allSpecialFees));
    $sumAdd = array_sum(array_map(fn($f) => $f['fee_type'] === 'addition' ? (float)$f['amount'] : 0, $allSpecialFees));
  ?>
  <div class="px-5 py-3 border-t border-ak-border flex gap-6 text-sm">
    <span class="text-ak-muted"><?= count($allSpecialFees) ?> fee(s) total</span>
    <?php if ($sumDed > 0): ?>
    <span class="font-mono text-ak-red font-bold">−¥<?= number_format($sumDed) ?> deductions</span>
    <?php endif; ?>
    <?php if ($sumAdd > 0): ?>
    <span class="font-mono text-ak-green font-bold">+¥<?= number_format($sumAdd) ?> additions</span>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>



<?php elseif ($tab === 'statements'): ?>
<div class="flex justify-between items-center mb-5 flex-wrap gap-3">
  <h2 class="text-lg font-bold">Settlement Statements — <?= h($auction['name']) ?></h2>
  <div class="flex gap-2">
    <a class="btn btn-dark" href="pdf.php?all=1&v=3.0&auction_id=<?= $activeAuctionId ?>" target="_blank">↓ Print All PDFs</a>
    <a class="btn btn-dark" href="api/download_pdf_zip.php?auction_id=<?= (int)$activeAuctionId ?>" onclick="showToast('📦 Preparing ZIP download...','info',3000)">📦 Download ZIP</a>
    <a href="auction_summary.php?auction_id=<?= (int)$activeAuctionId ?>" target="_blank" class="btn btn-dark">📊 Auction Summary</a>
  </div>
</div>

<!-- Search & Filter -->
<div class="flex items-center gap-2 mb-4">
  <div class="vehicles-search-wrap flex-1 min-w-[200px]">
    <div class="search-icon-wrap">
      <span class="search-icon">🔍</span>
      <input type="text" id="statement-search" class="vehicles-search-input" placeholder="Search by member name..." autocomplete="off">
    </div>
  </div>
  <select id="payment-filter" class="inp text-xs py-1.5 px-2 w-auto" onchange="filterStatements()">
    <option value="all">All</option>
    <option value="paid">✓ Paid</option>
    <option value="unpaid">✗ Unpaid</option>
    <option value="partial">◑ Partial</option>
  </select>
</div>

<?php
$totalPaid = 0; $totalUnpaid = 0; $totalPartial = 0; $totalNetPayout = 0;
foreach ($members as $m) {
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300), $memberFeesAll[$m['id']] ?? []);
    if ($s['count'] === 0) continue;
    $ps = $paymentStatuses[$m['id']] ?? null;
    $payStatus = $ps['status'] ?? 'unpaid';
    $totalNetPayout += $s['netPayout'];
    if ($payStatus === 'paid') $totalPaid++;
    elseif ($payStatus === 'partial') $totalPartial++;
    else $totalUnpaid++;
}
?>

<!-- Payment Summary -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
  <div class="bg-ak-card rounded-xl p-4 border border-ak-border text-center">
    <div class="text-2xl font-bold font-mono text-ak-gold"><?= fmt($totalNetPayout) ?></div>
    <div class="text-ak-muted text-xs mt-1">Total Net Payout</div>
  </div>
  <div class="bg-ak-card rounded-xl p-4 border border-ak-green/30 text-center">
    <div class="text-2xl font-bold font-mono text-ak-green"><?= $totalPaid ?></div>
    <div class="text-ak-muted text-xs mt-1">✓ Paid</div>
  </div>
  <div class="bg-ak-card rounded-xl p-4 border border-yellow-500/30 text-center">
    <div class="text-2xl font-bold font-mono text-yellow-400"><?= $totalPartial ?></div>
    <div class="text-ak-muted text-xs mt-1">◑ Partial</div>
  </div>
  <div class="bg-ak-card rounded-xl p-4 border border-ak-red/30 text-center">
    <div class="text-2xl font-bold font-mono text-ak-red"><?= $totalUnpaid ?></div>
    <div class="text-ak-muted text-xs mt-1">✗ Unpaid</div>
  </div>
</div>

<!-- Statements Grid (2 columns) -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-5" id="statements-container" style="min-height:400px">
<?php if (empty($members)): ?>
  <div class="bg-ak-card rounded-xl p-8 text-center text-ak-muted border border-ak-border md:col-span-2">No sales history available for this auction.</div>
<?php else: ?>
  <?php $hasSales = false; ?>
  <?php foreach ($members as $m):
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300), $memberFeesAll[$m['id']] ?? []);
    if ($s['count'] === 0) continue;
    $hasSales = true;
    $ps = $paymentStatuses[$m['id']] ?? null;
    $payStatus = $ps['status'] ?? 'unpaid';
    $payClass = match($payStatus) {
        'paid' => 'bg-ak-green/15 text-ak-green border-ak-green/30',
        'partial' => 'bg-yellow-500/15 text-yellow-400 border-yellow-500/30',
        default => 'bg-ak-red/10 text-ak-red border-ak-red/20',
    };
    $payIcon = match($payStatus) {
        'paid' => '✓ Paid',
        'partial' => '◑ Partial',
        default => '✗ Unpaid',
    };
  ?>
  <div class="bg-ak-card rounded-xl border border-ak-border overflow-hidden animate-fade-in-up statement-card" data-member-name="<?= h(mb_strtolower($m['name'])) ?>" data-payment="<?= $payStatus ?>">
    <div class="sh">
      <div><div class="sn2"><?= h($m['name']) ?></div><div class="sm"><?= h($m['email']) ?> · <?= h($m['phone']) ?></div>
      <?php if ($payStatus === 'paid' && $ps['paid_at']): ?>
        <div class="text-ak-green text-[11px] mt-0.5">✓ Paid on <?= date('Y-m-d H:i', strtotime($ps['paid_at'])) ?></div>
      <?php endif; ?>
      </div>
      <div class="sa">
        <div class="relative" id="pay-wrap-<?= (int)$m['id'] ?>">
          <button onclick="togglePaymentMenu(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, <?= $s['netPayout'] ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border cursor-pointer transition-all <?= $payClass ?>" id="pay-btn-<?= (int)$m['id'] ?>">
            <?= $payIcon ?>
            <span class="text-[10px] opacity-60">▾</span>
          </button>
          <div id="pay-menu-<?= (int)$m['id'] ?>" class="hidden absolute right-0 top-full mt-1 bg-ak-card border border-ak-border rounded-xl shadow-2xl z-50 min-w-[200px] overflow-hidden">
            <div class="px-3 py-2 border-b border-ak-border"><div class="text-ak-muted text-[10px] uppercase tracking-wider">Update Payment Status</div></div>
            <button onclick="setPaymentStatus(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, 'paid', <?= $s['netPayout'] ?>)" class="w-full px-4 py-2.5 text-left text-sm text-ak-green hover:bg-ak-green/10 transition-colors flex items-center gap-2">✓ Mark as Paid</button>
            <button onclick="setPaymentStatus(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, 'partial', <?= $s['netPayout'] ?>)" class="w-full px-4 py-2.5 text-left text-sm text-yellow-400 hover:bg-yellow-500/10 transition-colors flex items-center gap-2">◑ Mark as Partial</button>
            <button onclick="setPaymentStatus(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, 'unpaid', 0)" class="w-full px-4 py-2.5 text-left text-sm text-ak-red hover:bg-ak-red/10 transition-colors flex items-center gap-2">✗ Mark as Unpaid</button>
            <?php if ($ps && $ps['notes']): ?>
            <div class="px-4 py-2 border-t border-ak-border text-ak-muted text-xs italic">Note: <?= h($ps['notes']) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <button onclick="sendStatementEmail(<?= (int)$m['id'] ?>, <?= (int)$activeAuctionId ?>, this)" class="btn-email" id="email-btn-<?= (int)$m['id'] ?>">✉ Send Email</button>
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
        <?php if (!empty($s['specialFees'])): ?>
        <?php foreach ($s['specialFees'] as $sf): $isAdd = $sf['fee_type'] === 'addition'; ?>
        <div class="dr"><span class="dr-l flex items-center gap-1"><?= $isAdd ? '➕' : '➖' ?> <?= h($sf['fee_name']) ?><?php if (!empty($sf['notes'])): ?> <span class="text-ak-muted text-[10px]">(<?= h($sf['notes']) ?>)</span><?php endif; ?></span><span class="dr-a <?= $isAdd ? 'text-ak-green' : '' ?>"><?= $isAdd ? '+' : '−' ?>¥<?= number_format((float)$sf['amount']) ?></span></div>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php endif; ?>
        <div class="dt"><span class="dt-l">Total Deductions</span><span class="dt-n">−<?= fmt($s['totalDed']) ?></span></div>
        <div class="np"><span class="np-l">NET PAYOUT / お支払い額</span><span class="np-n"><?= fmt($s['netPayout']) ?></span></div>
      </div>

      <?php
      $memberHistory = $stmtHistory[$m['id']] ?? [];
      if (!empty($memberHistory)):
      ?>
      <div class="border-t border-ak-border mt-0">
        <button onclick="toggleStmtHistory(<?= (int)$m['id'] ?>)" class="w-full px-6 py-2.5 flex items-center justify-between text-xs text-ak-muted hover:text-ak-text2 hover:bg-ak-infield/50 transition-colors">
          <span>📋 Statement History (<?= count($memberHistory) ?> records)</span>
          <span id="stmt-history-arrow-<?= (int)$m['id'] ?>">▾</span>
        </button>
        <div id="stmt-history-<?= (int)$m['id'] ?>" class="hidden border-t border-ak-border/50">
        <?php foreach ($memberHistory as $h): $isEmail = $h['action'] === 'email'; ?>
          <div class="flex items-center gap-3 px-6 py-2 text-xs border-b border-ak-border/30 last:border-0 hover:bg-ak-infield/30 transition-colors">
            <span class="<?= $isEmail ? 'text-ak-gold' : 'text-ak-text2' ?>"><?= $isEmail ? '✉️' : '📄' ?></span>
            <span class="text-ak-text2 font-medium"><?= $isEmail ? 'Email sent' : 'PDF generated' ?></span>
            <span class="text-ak-muted font-mono"><?= fmt($h['net_payout']) ?></span>
            <span class="text-ak-muted ml-auto font-mono"><?= date('Y-m-d H:i', strtotime($h['created_at'])) ?></span>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
  <?php endforeach; ?>
  <?php if (!$hasSales): ?>
  <div class="bg-ak-card rounded-xl p-8 text-center text-ak-muted border border-ak-border md:col-span-2">No sold vehicles recorded for this auction yet.</div>
  <?php endif; ?>
<?php endif; ?>
</div>

<script>
document.getElementById('statement-search')?.addEventListener('input', function() {
  filterStatements();
});

function filterStatements() {
  const q = (document.getElementById('statement-search')?.value || '').toLowerCase().trim();
  const payFilter = document.getElementById('payment-filter')?.value || 'all';
  document.querySelectorAll('.statement-card').forEach(card => {
    const name = card.getAttribute('data-member-name') || '';
    const payment = card.getAttribute('data-payment') || 'unpaid';
    const matchSearch = !q || name.includes(q);
    const matchPayment = payFilter === 'all' || payment === payFilter;
    card.style.display = (matchSearch && matchPayment) ? '' : 'none';
  });
}
</script>

<?php endif; ?>
<script>const membersData = <?= json_encode(array_map(fn($m) => ['id'=>(int)$m['id'], 'name'=>$m['name'], 'phone'=>$m['phone']], $members)) ?>;const activeAuctionId = <?= (int)$activeAuctionId ?>;const CSRF_TOKEN = '<?= h($tok) ?>';</script>
<script src="js/app.js?v=3.5"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof VehiclesPager !== 'undefined' && document.getElementById('vehicles-tbody')) {
    VehiclesPager.init(<?= (int)$activeAuctionId ?>);
  }
  if (typeof MembersPager !== 'undefined' && document.getElementById('members-list-container')) {
    MembersPager.init(<?= (int)$activeAuctionId ?>);
  }
  <?php
  $tsRows = $db->query("SELECT `key`, value FROM settings WHERE `key` IN ('session_timeout_enabled','session_timeout_minutes','session_timeout_warn_minutes')")->fetchAll(PDO::FETCH_KEY_PAIR);
  if (($tsRows['session_timeout_enabled'] ?? '1') === '1'): ?>
  SessionTimeout.init(<?= (int)($tsRows['session_timeout_minutes'] ?? 30) ?>, <?= (int)($tsRows['session_timeout_warn_minutes'] ?? 2) ?>);
  <?php else: ?>
  SessionTimeout.enabled = false;
  <?php endif; ?>
});
</script>

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

<!-- Remove Member Modal -->
<div id="removeMemberModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl w-[95%] max-w-[420px] p-7 shadow-2xl relative animate-fade-in-up">
    <div class="flex items-center justify-between mb-5">
      <h3 class="text-ak-red text-lg font-bold">🗑 Remove Member</h3>
      <button class="text-ak-muted text-2xl hover:text-ak-text hover:bg-ak-infield px-2 py-1 rounded-lg transition-all" onclick="closeRemoveMemberModal()">×</button>
    </div>
    <div class="mb-5">
      <div class="text-ak-text text-sm mb-2">Are you sure you want to remove <b id="removeMemberName" class="text-ak-gold"></b>?</div>
      <div class="text-ak-muted text-xs">This will also remove all their vehicles from this auction. This cannot be undone.</div>
    </div>
    <div class="flex justify-end gap-2 pt-4 border-t border-ak-border">
      <button class="btn btn-dark btn-sm" onclick="closeRemoveMemberModal()">Cancel</button>
      <button class="btn btn-sm bg-ak-red/15 text-ak-red border border-ak-red/30 hover:bg-ak-red/25" id="confirmRemoveMemberBtn" onclick="confirmRemoveMember()">Remove</button>
    </div>
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

<!-- Edit Fee Modal -->
<div id="editFeeModal" class="fixed inset-0 bg-black/85 backdrop-blur-md z-[99999] items-center justify-center" style="display:none">
  <div class="bg-ak-card border border-ak-border rounded-2xl p-8 max-w-[520px] w-[92%] shadow-2xl max-h-[90vh] overflow-y-auto">
    <h3 class="text-ak-gold font-bold text-lg mb-5">✎ Edit Fee</h3>
    <form id="editFeeForm" data-parsley-validate>
      <input type="hidden" id="ef_feeId">
      <input type="hidden" id="ef_memberId">
      <div class="mb-4"><label class="lbl">Fee Name *</label><input class="inp" id="ef_feeName" data-parsley-required="true" data-parsley-required-message="Fee name is required"></div>
      <div class="grid grid-cols-2 gap-3 mb-4">
        <div><label class="lbl">Amount (¥) *</label><input class="inp font-mono" type="number" id="ef_amount" min="1" data-parsley-required="true" data-parsley-type="number" data-parsley-min="1" data-parsley-required-message="Amount is required"></div>
        <div><label class="lbl">Type</label><select class="inp" id="ef_feeType"><option value="deduction">− Deduction</option><option value="addition">+ Addition</option></select></div>
      </div>
      <div class="mb-5"><label class="lbl">Notes</label><input class="inp" id="ef_notes" placeholder="Optional"></div>
      <div class="flex gap-3">
        <button type="button" onclick="closeEditFeeModal()" class="btn btn-dark flex-1">Cancel</button>
        <button type="submit" id="editFeeBtn" class="btn btn-gold flex-1">💾 Save Changes</button>
      </div>
    </form>
  </div>
</div>

</body>
</html>
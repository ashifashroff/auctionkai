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
// Clean expired statement links
try {
    $db->exec("DELETE FROM statement_links WHERE expires_at < NOW()");
} catch (Exception $e) {
    // Never crash on cleanup
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


require_once __DIR__ . '/includes/post_handlers.php';

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

// Count members in this auction only
$membersInAuction = count(array_unique(array_column($vehicles, 'member_id')));

// Count unpaid members for dashboard
$totalUnpaid = 0;
foreach ($members as $m) {
    $s = calcStatement((int)$m['id'], $vehicles, (float)($auction['commission_fee'] ?? 3300), $memberFeesAll[$m['id']] ?? []);
    if ($s['count'] === 0) continue;
    $ps = $paymentStatuses[$m['id']] ?? null;
    if (($ps['status'] ?? 'unpaid') === 'unpaid') $totalUnpaid++;
}
?>

<?php require_once 'views/partials/head.php'; ?>


<?php include 'views/partials/topbar.php'; ?>

<?php include 'views/partials/auction_bar.php'; ?>

<?php include 'views/partials/tabs.php'; ?>
<!-- ─── CONTENT ─────────────────────────────────────── -->
<div class="px-4 md:px-7 py-4 md:py-7 max-w-[1400px] mx-auto animate-fade-in pb-20 md:pb-7">

<?php if (!$auction): ?>
  <div class="text-center py-24 animate-fade-in-up">
    <div class="text-6xl mb-6">🏷</div>
    <h2 class="text-2xl font-bold text-ak-text mb-3">No Auctions Yet</h2>
    <p class="text-ak-muted mb-6 max-w-md mx-auto">Create your first auction to start managing members, vehicles, and settlement statements.</p>
    <button onclick="document.getElementById('addAuctionForm').classList.remove('hidden')" class="btn btn-gold btn-sm">+ Create Your First Auction</button>
  </div>


<?php elseif ($tab === 'dashboard'): ?>
<?php include 'views/dashboard.php'; ?>

<?php elseif ($tab === 'members'): ?>
<?php include 'views/members.php'; ?>

<?php elseif ($tab === 'vehicles'): ?>
<?php include 'views/vehicles.php'; ?>

<?php elseif ($tab === 'special_fees'): ?>
<?php include 'views/special_fees.php'; ?>

<?php elseif ($tab === 'statements'): ?>
<?php include 'views/statements.php'; ?>
<?php endif; ?>
</div>

<script>const membersData = <?= json_encode(array_map(fn($m) => ['id'=>(int)$m['id'], 'name'=>$m['name'], 'phone'=>$m['phone']], $members)) ?>;const activeAuctionId = <?= (int)$activeAuctionId ?>;const CSRF_TOKEN = '<?= h($tok) ?>';</script>
<script src="js/common.js?v=3.6"></script>
<script src="js/vehicles.js?v=3.6"></script>
<script src="js/members.js?v=3.6"></script>
<script src="js/statements.js?v=3.6"></script>
<script src="js/fees.js?v=3.6"></script>
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


<?php require_once 'views/partials/modals.php'; ?>

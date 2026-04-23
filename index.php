<?php
require_once 'config.php';
session_start();
if (empty($_SESSION['tok'])) $_SESSION['tok'] = bin2hex(random_bytes(16));
$tok = $_SESSION['tok'];

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
$allAuctions = $db->query("SELECT * FROM auction ORDER BY date DESC, id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['auction_id'])) {
    $_SESSION['auction_id'] = (int)$_GET['auction_id'];
}
if (empty($_SESSION['auction_id']) && !empty($allAuctions)) {
    $_SESSION['auction_id'] = (int)$allAuctions[0]['id'];
}
$activeAuctionId = (int)($_SESSION['auction_id'] ?? 0);
$auction = null;
if ($activeAuctionId) {
    $stmt = $db->prepare("SELECT * FROM auction WHERE id=?");
    $stmt->execute([$activeAuctionId]);
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
            $stmt = $db->prepare("INSERT INTO auction (name, date, location) VALUES (?,?,?)");
            $stmt->execute([$name, $date, $location]);
            $newId = (int)$db->lastInsertId();
            // Create default fees for new auction
            $db->prepare("INSERT INTO fees (auction_id, entry_fee, commission_rate, tax_rate, transport_fee) VALUES (?,?,?,?)")
               ->execute([$newId, 3000, 3.00, 10.00, 5000]);
            $_SESSION['auction_id'] = $newId;
        }
    }

    elseif ($action === 'delete_auction') {
        $id = (int)$_POST['id'];
        $db->prepare("DELETE FROM auction WHERE id=?")->execute([$id]);
        unset($_SESSION['auction_id']);
    }

    elseif ($action === 'save_auction') {
        $stmt = $db->prepare("UPDATE auction SET name=?, date=?, location=? WHERE id=?");
        $stmt->execute([trim($_POST['name']), trim($_POST['date']), trim($_POST['location'] ?? ''), $activeAuctionId]);
    }

    elseif ($action === 'add_member') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $stmt = $db->prepare("INSERT INTO members (auction_id, name, phone, email) VALUES (?,?,?,?)");
            $stmt->execute([$activeAuctionId, $name, trim($_POST['phone'] ?? ''), trim($_POST['email'] ?? '')]);
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
            $stmt = $db->prepare("INSERT INTO vehicles (member_id, make, model, year, lot, sold_price, sold) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([
                $memberId, $make,
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
$members  = $activeAuctionId
    ? $db->query("SELECT * FROM members WHERE auction_id=" . (int)$activeAuctionId . " ORDER BY id")->fetchAll()
    : [];
$vehicles = $activeAuctionId
    ? $db->query("SELECT v.* FROM vehicles v JOIN members m ON v.member_id = m.id WHERE m.auction_id=" . (int)$activeAuctionId . " ORDER BY v.id")->fetchAll()
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
<style>
:root {
    --bg:#0A1420;--bg2:#07101A;--card:#111E2D;--border:#1E3A5F;
    --gold:#D4A84B;--text:#E8DCC8;--text2:#A8C4D8;--muted:#6A88A0;
    --muted2:#3A5570;--green:#4CAF82;--red:#CC7777;--infield:#0A1724;
    --mono:'Space Mono',monospace;--sans:'Noto Sans JP',sans-serif;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--sans);background:var(--bg);color:var(--text);min-height:100vh;font-size:13px;line-height:1.5}
a{color:inherit;text-decoration:none}
button,input,select,textarea{font-family:var(--sans)}

.topbar{background:var(--bg2);border-bottom:1px solid var(--border);padding:14px 28px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;position:sticky;top:0;z-index:100}
.brand{font-size:22px;font-weight:700;color:var(--gold);letter-spacing:-0.5px}
.brand-sub{font-size:11px;color:var(--muted);margin-top:1px;letter-spacing:1px;text-transform:uppercase}
.topbar form{display:flex;gap:10px;align-items:center}

/* Auction selector */
.auction-bar{background:var(--bg2);border-bottom:1px solid var(--border);padding:10px 28px;display:flex;align-items:center;gap:16px;flex-wrap:wrap;position:sticky;top:68px;z-index:99}
.auction-select{display:flex;gap:8px;flex-wrap:wrap;flex:1;align-items:center}
.auction-chip{padding:7px 16px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid var(--border);background:var(--infield);color:var(--muted);cursor:pointer;white-space:nowrap;transition:all .15s;text-decoration:none;display:inline-flex;align-items:center;gap:6px}
.auction-chip:hover{border-color:var(--gold);color:var(--text2)}
.auction-chip.active{background:var(--gold);color:#0A1420;border-color:var(--gold)}
.auction-chip .chip-loc{font-size:10px;opacity:.7}
.auction-add{padding:7px 14px;border-radius:20px;font-size:12px;font-weight:700;border:1px dashed var(--border);background:none;color:var(--muted);cursor:pointer;transition:all .15s}
.auction-add:hover{border-color:var(--gold);color:var(--gold)}
.auction-meta{font-size:11px;color:var(--muted);white-space:nowrap}
.auction-meta b{color:var(--gold)}

.tabs{background:var(--bg2);border-bottom:1px solid var(--border);display:flex;align-items:center;padding-left:16px;overflow-x:auto}
.tab-btn{padding:13px 20px;font-size:13px;font-weight:400;color:var(--muted);border:none;border-bottom:2px solid transparent;background:none;cursor:pointer;white-space:nowrap;text-decoration:none;display:inline-block;transition:color .15s}
.tab-btn:hover{color:var(--text2)}
.tab-btn.active{color:var(--gold);border-bottom-color:var(--gold);font-weight:700}
.tab-stats{margin-left:auto;padding:0 24px;font-size:12px;color:var(--muted);display:flex;gap:16px;align-items:center;white-space:nowrap}
.tab-stats b{color:var(--gold)} .tab-stats b.g{color:var(--green)}

.content{padding:28px;max-width:1150px;margin:0 auto}
h2{font-size:17px;font-weight:700;margin-bottom:20px}

.card{background:var(--card);border:1px solid var(--border);border-radius:14px}
.card-pad{padding:20px 24px}

.inp,select.inp{background:var(--infield);border:1px solid var(--border);border-radius:8px;padding:8px 12px;color:var(--text);font-size:13px;outline:none;width:100%;transition:border-color .15s}
.inp:focus,select.inp:focus{border-color:var(--gold)}
.inp.mono{font-family:var(--mono);text-align:right;color:var(--gold)}
.lbl{font-size:11px;color:var(--muted);display:block;margin-bottom:4px;font-weight:500}

.btn{border:none;border-radius:8px;padding:8px 20px;font-size:13px;font-weight:700;cursor:pointer;font-family:var(--sans);white-space:nowrap}
.btn-gold{background:var(--gold);color:#0A1420} .btn-gold:hover{background:#E0B85A}
.btn-dark{background:var(--infield);color:var(--gold);border:1px solid var(--border)} .btn-dark:hover{border-color:var(--gold)}
.btn-ghost{background:none;border:1px solid #3A2020;color:var(--red)} .btn-ghost:hover{background:#2A1010}
.btn-icon{background:none;border:none;color:var(--red);cursor:pointer;font-size:20px;line-height:1;padding:0 6px}
.btn-sm{padding:6px 14px;font-size:12px}
.btn-email{background:#0F2030;color:var(--gold);border:1px solid var(--border);padding:8px 16px;font-weight:600;font-size:12px;border-radius:8px;cursor:pointer;white-space:nowrap;display:inline-block}
.btn-email:hover{border-color:var(--gold)}

.add-row{display:grid;gap:12px;align-items:end;margin-bottom:16px}
.ar-members{grid-template-columns:2fr 1.5fr 2fr auto}
.ar-vehicles{grid-template-columns:2fr 1.5fr 1.5fr 0.8fr 1fr 1.5fr auto}
.ar-auction{grid-template-columns:2fr 1fr 1.5fr auto}
@media(max-width:900px){.ar-members{grid-template-columns:1fr 1fr}.ar-vehicles{grid-template-columns:1fr 1fr 1fr}.ar-auction{grid-template-columns:1fr 1fr}}
@media(max-width:600px){.ar-members,.ar-vehicles,.ar-auction{grid-template-columns:1fr}.topbar form{flex-wrap:wrap}}

.sec-lbl{font-size:11px;font-weight:700;letter-spacing:2px;color:var(--gold);margin-bottom:14px;text-transform:uppercase}

/* Members */
.mi{display:flex;align-items:center;gap:16px;padding:16px 20px;animation:fi .2s ease}
.av{width:42px;height:42px;border-radius:50%;background:#1E3A5F;display:flex;align-items:center;justify-content:center;color:var(--gold);font-weight:700;font-size:17px;flex-shrink:0}
.mn{font-weight:600;font-size:15px;color:#F0E4C8}
.mm{font-size:12px;color:var(--muted);margin-top:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ms{text-align:center;padding:0 20px;border-left:1px solid var(--border);border-right:1px solid var(--border);flex-shrink:0}
.ms-big{font-size:22px;font-weight:700;color:var(--gold);font-family:var(--mono)}
.ms-sm{font-size:11px;color:var(--muted)}
.mp{text-align:right;padding:0 20px;border-right:1px solid var(--border);flex-shrink:0}
.mp-num{font-size:17px;font-weight:700;color:var(--green);font-family:var(--mono)}

/* Vehicles table */
.vt{width:100%;border-collapse:collapse;font-size:13px}
.vt th{padding:12px 16px;text-align:left;color:var(--muted);font-weight:600;font-size:10px;letter-spacing:1.5px;text-transform:uppercase;border-bottom:1px solid var(--border)}
.vt th.r{text-align:right}
.vt td{padding:11px 16px;border-bottom:1px solid #131F2E}
.vt tr:last-child td{border-bottom:none}
.vt tr:hover td{background:rgba(30,58,95,.2)}
.lot{color:var(--gold);font-family:var(--mono);font-size:11px}
.sb{padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;border:none;cursor:pointer;font-family:var(--sans)}
.sy{background:#1A3A2A;color:var(--green)} .sn{background:#3A1A1A;color:var(--red)}

/* Fee settings */
.fr{display:flex;align-items:center;gap:16px;padding:12px 16px;background:var(--infield);border-radius:10px;margin-bottom:10px}
.fr-lbl{flex:1} .fr-name{font-size:13px;font-weight:600;color:var(--text)} .fr-note{font-size:11px;color:var(--muted);margin-top:2px}
.fr-inp{display:flex;align-items:center;gap:6px} .fr-unit{color:var(--muted);font-size:13px}
.ci{display:flex;align-items:center;gap:12px;padding:10px 14px;background:var(--infield);border-radius:8px;margin-bottom:8px}
.ci-name{flex:1;font-size:13px;color:var(--text)} .ci-amt{font-family:var(--mono);color:var(--gold);font-size:13px}
.add-ci{display:flex;gap:10px;margin-top:12px}

/* Statements */
.sh{padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px}
.sn2{font-size:17px;font-weight:700;color:#F0E4C8} .sm{font-size:12px;color:var(--muted);margin-top:2px}
.sa{display:flex;gap:10px}
.sb2{display:grid;grid-template-columns:1fr 1fr}
.sl{padding:20px 24px;border-right:1px solid var(--border)}
.sr{padding:20px 24px}
.ssl{font-size:10px;font-weight:700;letter-spacing:2px;color:var(--muted);margin-bottom:12px;text-transform:uppercase}
.vr{display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid #131F2E}
.vr-lot{color:var(--gold);font-family:var(--mono);font-size:11px;margin-right:6px}
.vr-car{color:var(--text2)} .vr-yr{color:var(--muted)} .vr-p{font-family:var(--mono);color:var(--green);flex-shrink:0;margin-left:8px}
.sg{display:flex;justify-content:space-between;padding:10px 0 0;font-weight:700}
.sg-l{color:var(--muted);font-size:13px} .sg-n{font-family:var(--mono);color:var(--text);font-size:15px}
.dr{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #131F2E}
.dr-l{color:var(--muted)} .dr-l.dim{color:var(--muted2)} .dr-a{font-family:var(--mono);color:var(--red);flex-shrink:0;margin-left:8px}
.dt{display:flex;justify-content:space-between;padding:8px 0}
.dt-l{font-weight:700;color:var(--red);font-size:12px} .dt-n{font-family:var(--mono);font-weight:700;color:var(--red)}
.np{margin-top:12px;background:var(--gold);border-radius:10px;padding:14px 18px;display:flex;justify-content:space-between;align-items:center}
.np-l{font-size:12px;font-weight:700;color:#0A1420;letter-spacing:.5px}
.np-n{font-size:20px;font-weight:700;font-family:var(--mono);color:#0A1420}
.se{padding:28px;color:var(--muted);font-size:13px;text-align:center}
.nm{text-align:center;padding:60px;color:var(--muted)}
.st-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px}

/* DB badge */
.db-badge{background:#1A3A1A;border:1px solid #2A5A2A;color:#4CAF82;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;margin-left:8px}

/* Auction edit panel */
.auction-edit{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-left:auto}
.auction-edit .inp{width:auto}

/* No auction state */
.no-auction{text-align:center;padding:80px 20px}
.no-auction h2{color:var(--gold);font-size:22px;margin-bottom:12px}
.no-auction p{color:var(--muted);font-size:14px}

@keyframes fi{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
</style>
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
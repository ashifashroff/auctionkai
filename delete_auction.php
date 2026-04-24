<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: /auctionkai/auth/login.php');
    exit;
}

$db = db();
$userId = (int)$_SESSION['user_id'];
$error = '';

function fmt(float $n): string {
    return '¥' . number_format(round($n));
}

// Get auction
$auctionId = (int)($_GET['auction_id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM auction WHERE id=? AND user_id=?");
$stmt->execute([$auctionId, $userId]);
$auction = $stmt->fetch();

if (!$auction) {
    header('Location: /auctionkai/index.php');
    exit;
}

// Get stats
$members = $db->prepare("SELECT COUNT(*) FROM members WHERE user_id=?");
$members->execute([$userId]);
$memberCount = (int)$members->fetchColumn();

$vehicles = $db->prepare("SELECT * FROM vehicles WHERE auction_id=?");
$vehicles->execute([$auctionId]);
$vehicleList = $vehicles->fetchAll();
$totalVehicles = count($vehicleList);
$soldCount = count(array_filter($vehicleList, fn($v) => $v['sold']));
$unsoldCount = $totalVehicles - $soldCount;
$grossSales = array_sum(array_map(fn($v) => $v['sold'] ? (float)$v['sold_price'] : 0, $vehicleList));

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['confirm'] ?? '') === 'yes') {
    // Delete vehicles for this auction
    $db->prepare("DELETE FROM vehicles WHERE auction_id=?")->execute([$auctionId]);
    // Delete the auction
    $db->prepare("DELETE FROM auction WHERE id=? AND user_id=?")->execute([$auctionId, $userId]);
    // Clear session if this was active
    if (($_SESSION['auction_id'] ?? 0) == $auctionId) {
        unset($_SESSION['auction_id']);
    }
    header('Location: /auctionkai/index.php?tab=dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>AuctionKai — Delete Auction</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@300;400;500;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
<?php include 'css/tailwind-config.php'; ?>
</head>
<body class="bg-ak-bg text-ak-text font-sans min-h-screen">

<div class="min-h-screen flex items-center justify-center px-4">
  <div class="w-full max-w-lg">

    <!-- Header -->
    <div class="text-center mb-8 animate-fade-in">
      <div class="text-2xl font-bold text-ak-red">⚠ Delete Auction</div>
      <p class="text-ak-muted text-sm mt-2">This action cannot be undone</p>
    </div>

    <!-- Warning Card -->
    <div class="bg-ak-card border border-ak-red/30 rounded-xl p-6 mb-5 animate-fade-in-up">
      <div class="text-ak-text text-lg font-bold mb-2"><?= h($auction['name']) ?></div>
      <div class="text-ak-muted text-sm mb-5">Created: <?= h($auction['date']) ?> · Expires: <?= h($auction['expires_at']) ?></div>

      <div class="bg-ak-red/10 border border-ak-red/20 rounded-lg p-4 mb-5">
        <div class="text-ak-red font-semibold text-sm mb-2">⚠ All data related to this auction will be permanently deleted:</div>
        <ul class="text-ak-text2 text-sm space-y-1 ml-4 list-disc">
          <li>All vehicles (sold & unsold)</li>
          <li>All fee records</li>
          <li>Auction settings & commission rate</li>
        </ul>
        <div class="text-ak-muted text-xs mt-2">✓ Members will NOT be deleted — they are shared across auctions.</div>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-4 gap-3 mb-5">
        <div class="bg-ak-bg rounded-lg p-3 text-center">
          <div class="text-2xl font-bold text-ak-text font-mono"><?= $totalVehicles ?></div>
          <div class="text-ak-muted text-[10px] uppercase tracking-wider">Vehicles</div>
        </div>
        <div class="bg-ak-bg rounded-lg p-3 text-center">
          <div class="text-2xl font-bold text-ak-green font-mono"><?= $soldCount ?></div>
          <div class="text-ak-muted text-[10px] uppercase tracking-wider">Sold</div>
        </div>
        <div class="bg-ak-bg rounded-lg p-3 text-center">
          <div class="text-2xl font-bold text-ak-red font-mono"><?= $unsoldCount ?></div>
          <div class="text-ak-muted text-[10px] uppercase tracking-wider">Unsold</div>
        </div>
        <div class="bg-ak-bg rounded-lg p-3 text-center">
          <div class="text-lg font-bold text-ak-gold font-mono"><?= fmt($grossSales) ?></div>
          <div class="text-ak-muted text-[10px] uppercase tracking-wider">Gross</div>
        </div>
      </div>

      <!-- Confirm Form -->
      <form method="POST" action="delete_auction.php?auction_id=<?= $auctionId ?>" data-parsley-validate>
        <input type="hidden" name="confirm" value="yes">
        <div class="flex gap-3">
          <a href="/auctionkai/index.php?tab=dashboard&auction_id=<?= $auctionId ?>" class="btn btn-dark flex-1 text-center">← Cancel & Go Back</a>
          <button class="btn flex-1 text-center" type="submit" style="background:var(--red);color:#fff" onclick="return confirm('Are you absolutely sure? This will permanently delete this auction and all its vehicles.')">🗑 Confirm Delete</button>
        </div>
      </form>
    </div>

  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/parsleyjs@2.9.2/dist/parsley.min.js"></script>
</body>
</html>
